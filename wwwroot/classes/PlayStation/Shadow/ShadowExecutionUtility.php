<?php

declare(strict_types=1);

require_once __DIR__ . '/ShadowResponseComparator.php';

final class ShadowExecutionTimeoutException extends RuntimeException
{
}

final class ShadowTimeoutSupportUnavailableException extends RuntimeException
{
}

final class ShadowExecutionUtility
{
    private const string EXECUTE_WITHOUT_TIMEOUT_ENV = 'PSN_SHADOW_EXECUTE_WITHOUT_TIMEOUT';
    private const float DEFAULT_MISMATCH_SAMPLE_RATE = 0.2;
    private const int DEFAULT_MISMATCH_RATE_LIMIT_PER_MINUTE = 60;
    private const int MAX_MISMATCH_SAMPLES = 3;
    private const string DEFAULT_MISMATCH_RATE_LIMIT_STORE_DIR_NAME = 'psn_shadow_observability';
    private const string DEFAULT_MISMATCH_RATE_LIMIT_STORE_FILE_NAME = 'mismatch_rate_limit.json';

    /** @var callable(array<string, mixed>): void|null */
    private static $eventEmitter = null;

    /**
     * @var array<string, array{minuteWindow: int, count: int}>
     */
    private static array $mismatchRateLimitState = [];

    /**
     * @template T
     * @param callable(): T $legacyExecutor
     * @param callable(): mixed $shadowExecutor
     * @param callable(mixed): array<string, mixed> $normalizer
     * @param array<string, mixed> $metricTags
     * @return T
     */
    public static function executeWithLegacyTruth(
        PsnClientMode $mode,
        string $operation,
        callable $legacyExecutor,
        callable $shadowExecutor,
        callable $normalizer,
        int $shadowLatencyBudgetMs = 350,
        array $metricTags = []
    ): mixed {
        $legacyStart = hrtime(true);
        $legacyResponse = $legacyExecutor();
        $legacyDurationMs = (int) ((hrtime(true) - $legacyStart) / 1_000_000);

        if (!$mode->isShadow()) {
            return $legacyResponse;
        }

        if ($legacyDurationMs >= $shadowLatencyBudgetMs) {
            self::emitEvent(array_merge($metricTags, [
                'event' => 'psn_shadow_skipped',
                'operation' => $operation,
                'reason' => 'legacy_latency_budget_exhausted',
                'legacyDurationMs' => $legacyDurationMs,
                'shadowLatencyBudgetMs' => $shadowLatencyBudgetMs,
            ]));

            return $legacyResponse;
        }

        $remainingShadowBudgetMs = $shadowLatencyBudgetMs - $legacyDurationMs;

        $shadowStart = hrtime(true);
        try {
            $shadowResponse = self::executeShadowWithTimeout($shadowExecutor, $remainingShadowBudgetMs);
            $shadowDurationMs = (int) ((hrtime(true) - $shadowStart) / 1_000_000);

            try {
                $normalizedLegacyResponse = $normalizer($legacyResponse);
                $normalizedShadowResponse = $normalizer($shadowResponse);
            } catch (Throwable $normalizationException) {
                self::emitEvent(array_merge($metricTags, [
                    'event' => 'psn_shadow_comparison_result',
                    'service' => self::toNullableString($metricTags['service'] ?? null) ?? 'unknown_service',
                    'operation' => $operation,
                    'comparisonMetrics' => self::buildComparisonMetricsPayload(
                        totalCompared: 0,
                        matched: 0,
                        mismatched: 0,
                        skippedNormalizationFailure: 1,
                        newClientErrors: 0
                    ),
                    'normalizationErrorType' => $normalizationException::class,
                    'message' => $normalizationException->getMessage(),
                    'legacyDurationMs' => $legacyDurationMs,
                    'shadowDurationMs' => $shadowDurationMs,
                ]));

                return $legacyResponse;
            }

            $comparison = ShadowResponseComparator::compare(
                $normalizedLegacyResponse,
                $normalizedShadowResponse
            );

            self::emitEvent(array_merge($metricTags, [
                'event' => 'psn_shadow_comparison_result',
                'service' => self::toNullableString($metricTags['service'] ?? null) ?? 'unknown_service',
                'operation' => $operation,
                'comparisonMetrics' => self::buildComparisonMetricsPayload(
                    totalCompared: 1,
                    matched: $comparison['hasMismatch'] ? 0 : 1,
                    mismatched: $comparison['hasMismatch'] ? 1 : 0,
                    skippedNormalizationFailure: 0,
                    newClientErrors: 0
                ),
                'legacyDurationMs' => $legacyDurationMs,
                'shadowDurationMs' => $shadowDurationMs,
            ]));

            if ($comparison['hasMismatch']) {
                $service = self::toNullableString($metricTags['service'] ?? null) ?? 'unknown_service';
                $requestId = self::toNullableString($metricTags['requestId'] ?? null);
                $providedCorrelationId = self::toNullableString($metricTags['correlationId'] ?? null) ?? $requestId;
                $correlationId = $providedCorrelationId
                    ?? self::createCorrelationId();
                $eventRequestId = $requestId ?? $correlationId;
                $identifiers = self::buildIdentifiers($metricTags, $normalizedLegacyResponse, $normalizedShadowResponse);

                $rateLimitDecision = self::evaluateMismatchEmission(
                    $service,
                    $operation,
                    $identifiers,
                    $correlationId,
                    self::resolveMismatchSampleRate($metricTags),
                    self::resolveMismatchRateLimitPerMinute($metricTags),
                    self::resolveMismatchRateLimitStorePath($metricTags)
                );

                if ($rateLimitDecision['emit']) {
                    self::emitEvent(array_merge($metricTags, [
                        'event' => 'psn_shadow_mismatch',
                        'service' => $service,
                        'operation' => $operation,
                        'timestamp' => gmdate('c'),
                        'correlationId' => $correlationId,
                        'requestId' => $eventRequestId,
                        'legacyDurationMs' => $legacyDurationMs,
                        'shadowDurationMs' => $shadowDurationMs,
                        'identifiers' => $identifiers,
                        'diffSummary' => self::summarizeMismatch($comparison['mismatches']),
                        'sampling' => [
                            'sampleRate' => $rateLimitDecision['sampleRate'],
                            'rateLimitPerMinute' => $rateLimitDecision['rateLimitPerMinute'],
                            'samplingKey' => $rateLimitDecision['samplingKey'],
                        ],
                    ]));
                }
            }
        } catch (ShadowTimeoutSupportUnavailableException $unsupportedException) {
            self::emitEvent(array_merge($metricTags, [
                'event' => 'psn_shadow_skipped',
                'operation' => $operation,
                'reason' => 'shadow_timeout_support_unavailable',
                'legacyDurationMs' => $legacyDurationMs,
                'shadowLatencyBudgetMs' => $shadowLatencyBudgetMs,
                'message' => $unsupportedException->getMessage(),
            ]));
        } catch (ShadowExecutionTimeoutException $timeoutException) {
            self::emitEvent(array_merge($metricTags, [
                'event' => 'psn_shadow_skipped',
                'operation' => $operation,
                'reason' => 'shadow_latency_budget_exhausted',
                'legacyDurationMs' => $legacyDurationMs,
                'shadowLatencyBudgetMs' => $shadowLatencyBudgetMs,
                'message' => $timeoutException->getMessage(),
            ]));
        } catch (Throwable $shadowException) {
            self::emitEvent(array_merge($metricTags, [
                'event' => 'psn_shadow_comparison_result',
                'service' => self::toNullableString($metricTags['service'] ?? null) ?? 'unknown_service',
                'operation' => $operation,
                'comparisonMetrics' => self::buildComparisonMetricsPayload(
                    totalCompared: 0,
                    matched: 0,
                    mismatched: 0,
                    skippedNormalizationFailure: 0,
                    newClientErrors: 1
                ),
                'legacyDurationMs' => $legacyDurationMs,
                'shadowDurationMs' => (int) ((hrtime(true) - $shadowStart) / 1_000_000),
                'errorType' => $shadowException::class,
                'message' => $shadowException->getMessage(),
            ]));
            self::emitEvent(array_merge($metricTags, [
                'event' => 'psn_shadow_failure',
                'operation' => $operation,
                'legacyDurationMs' => $legacyDurationMs,
                'errorType' => $shadowException::class,
                'message' => $shadowException->getMessage(),
            ]));
        }

        return $legacyResponse;
    }

    /**
     * @return array{totalCompared: int, matched: int, mismatched: int, skippedNormalizationFailure: int, newClientErrors: int}
     */
    private static function buildComparisonMetricsPayload(
        int $totalCompared,
        int $matched,
        int $mismatched,
        int $skippedNormalizationFailure,
        int $newClientErrors
    ): array {
        return [
            'totalCompared' => $totalCompared,
            'matched' => $matched,
            'mismatched' => $mismatched,
            'skippedNormalizationFailure' => $skippedNormalizationFailure,
            'newClientErrors' => $newClientErrors,
        ];
    }

    /**
     * @param callable(array<string, mixed>): void|null $eventEmitter
     */
    public static function setEventEmitter(?callable $eventEmitter): void
    {
        self::$eventEmitter = $eventEmitter;
    }

    public static function resetStateForTests(): void
    {
        self::$eventEmitter = null;
        self::$mismatchRateLimitState = [];

        $storePaths = [];
        $storePath = getenv('PSN_SHADOW_MISMATCH_RATE_LIMIT_STORE_PATH');
        if (is_string($storePath) && $storePath !== '') {
            $storePaths[] = $storePath;
        }

        $defaultStorePath = self::resolveDefaultMismatchRateLimitStorePath();
        if ($defaultStorePath !== null) {
            $storePaths[] = $defaultStorePath;
        }

        foreach (array_unique($storePaths) as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private static function executeShadowWithTimeout(callable $shadowExecutor, int $shadowLatencyBudgetMs): mixed
    {
        if (
            !function_exists('pcntl_signal')
            || !function_exists('pcntl_async_signals')
            || !function_exists('pcntl_setitimer')
        ) {
            if (self::shouldExecuteWithoutTimeout()) {
                return $shadowExecutor();
            }

            throw new ShadowTimeoutSupportUnavailableException('Shadow timeout support is unavailable on this runtime.');
        }

        $budgetSeconds = $shadowLatencyBudgetMs / 1000;

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, static function (): void {
            throw new ShadowExecutionTimeoutException('Shadow execution exceeded latency budget.');
        });
        pcntl_setitimer(ITIMER_REAL, $budgetSeconds);

        try {
            return $shadowExecutor();
        } finally {
            pcntl_setitimer(ITIMER_REAL, 0.0);
            pcntl_async_signals(false);
            pcntl_signal(SIGALRM, SIG_DFL);
        }
    }

    private static function shouldExecuteWithoutTimeout(): bool
    {
        $configured = getenv(self::EXECUTE_WITHOUT_TIMEOUT_ENV);
        if (!is_string($configured)) {
            return false;
        }

        return filter_var($configured, FILTER_VALIDATE_BOOL) === true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function emitEvent(array $payload): void
    {
        if (is_callable(self::$eventEmitter)) {
            (self::$eventEmitter)($payload);

            return;
        }

        $encoded = json_encode($payload);

        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        error_log($encoded);
    }

    /**
     * @param array<string, mixed> $metricTags
     * @param array<string, mixed> $normalizedLegacyResponse
     * @param array<string, mixed> $normalizedShadowResponse
     * @return array{onlineId: string|null, accountId: string|null, npCommunicationId: string|null}
     */
    private static function buildIdentifiers(
        array $metricTags,
        array $normalizedLegacyResponse,
        array $normalizedShadowResponse
    ): array {
        return [
            'onlineId' => self::toNullableString(
                $metricTags['onlineId']
                    ?? self::findFirstValueByKey($normalizedLegacyResponse, 'onlineId')
                    ?? self::findFirstValueByKey($normalizedShadowResponse, 'onlineId')
            ),
            'accountId' => self::toNullableString(
                $metricTags['accountId']
                    ?? self::findFirstValueByKey($normalizedLegacyResponse, 'accountId')
                    ?? self::findFirstValueByKey($normalizedShadowResponse, 'accountId')
            ),
            'npCommunicationId' => self::toNullableString(
                $metricTags['npCommunicationId']
                    ?? self::findFirstValueByKey($normalizedLegacyResponse, 'npCommunicationId')
                    ?? self::findFirstValueByKey($normalizedShadowResponse, 'npCommunicationId')
            ),
        ];
    }

    /**
     * @param list<array{path: string, legacy: mixed, shadow: mixed, type: string}> $mismatches
     * @return array<string, mixed>
     */
    private static function summarizeMismatch(array $mismatches): array
    {
        $paths = [];
        $typeCounts = [];
        $sampledValues = [];

        foreach ($mismatches as $mismatch) {
            $path = isset($mismatch['path']) && is_string($mismatch['path']) ? $mismatch['path'] : '<root>';
            $type = isset($mismatch['type']) && is_string($mismatch['type']) ? $mismatch['type'] : 'value';

            $paths[] = $path;
            $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;

            if (count($sampledValues) >= self::MAX_MISMATCH_SAMPLES) {
                continue;
            }

            $sampledValues[] = [
                'path' => $path,
                'type' => $type,
                'legacy' => self::sanitizeSampleValue($path, $mismatch['legacy'] ?? null),
                'shadow' => self::sanitizeSampleValue($path, $mismatch['shadow'] ?? null),
            ];
        }

        return [
            'mismatchCount' => count($mismatches),
            'changedPaths' => array_values(array_unique($paths)),
            'typeCounts' => $typeCounts,
            'sampledValues' => $sampledValues,
        ];
    }

    private static function sanitizeSampleValue(string $path, mixed $value): mixed
    {
        $lowercasePath = strtolower($path);
        foreach (['npsso', 'token', 'secret', 'password', 'authorization', 'cookie'] as $sensitiveToken) {
            if (str_contains($lowercasePath, $sensitiveToken)) {
                return '<redacted>';
            }
        }

        if (is_scalar($value) || $value === null) {
            if (is_string($value) && strlen($value) > 128) {
                return self::truncateUtf8($value, 125) . '...';
            }

            return $value;
        }

        return sprintf('<%s>', gettype($value));
    }

    /**
     * @param array<string, mixed> $metricTags
     */
    private static function resolveMismatchSampleRate(array $metricTags): float
    {
        $sampleRate = $metricTags['mismatchSampleRate'] ?? getenv('PSN_SHADOW_MISMATCH_SAMPLE_RATE');

        if (!is_numeric($sampleRate)) {
            return self::DEFAULT_MISMATCH_SAMPLE_RATE;
        }

        return max(0.0, min(1.0, (float) $sampleRate));
    }

    /**
     * @param array<string, mixed> $metricTags
     */
    private static function resolveMismatchRateLimitPerMinute(array $metricTags): int
    {
        $limit = $metricTags['mismatchRateLimitPerMinute'] ?? getenv('PSN_SHADOW_MISMATCH_RATE_LIMIT_PER_MINUTE');

        if (!is_numeric($limit)) {
            return self::DEFAULT_MISMATCH_RATE_LIMIT_PER_MINUTE;
        }

        return max(1, (int) $limit);
    }

    /**
     * @param array<string, mixed> $metricTags
     */
    private static function resolveMismatchRateLimitStorePath(array $metricTags): ?string
    {
        $storePath = $metricTags['mismatchRateLimitStorePath'] ?? null;
        if ($storePath === null) {
            $envStorePath = getenv('PSN_SHADOW_MISMATCH_RATE_LIMIT_STORE_PATH');
            $storePath = $envStorePath === false
                ? self::resolveDefaultMismatchRateLimitStorePath()
                : $envStorePath;
        }

        return self::normalizeRateLimitStorePath(is_scalar($storePath) ? (string) $storePath : null);
    }

    private static function resolveDefaultMismatchRateLimitStorePath(): ?string
    {
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if ($tempDir === '') {
            return null;
        }

        $storeDir = $tempDir . DIRECTORY_SEPARATOR . self::DEFAULT_MISMATCH_RATE_LIMIT_STORE_DIR_NAME;
        if (!self::ensurePrivateStoreDirectory($storeDir)) {
            return null;
        }

        return $storeDir . DIRECTORY_SEPARATOR . self::DEFAULT_MISMATCH_RATE_LIMIT_STORE_FILE_NAME;
    }

    private static function truncateUtf8(string $value, int $maxBytes): string
    {
        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        if (function_exists('mb_strcut')) {
            $truncated = mb_strcut($value, 0, $maxBytes, 'UTF-8');

            return is_string($truncated) ? $truncated : '';
        }

        $truncated = substr($value, 0, $maxBytes);
        if (!is_string($truncated) || $truncated === '') {
            return '';
        }

        while ($truncated !== '' && !preg_match('//u', $truncated)) {
            $truncated = substr($truncated, 0, -1);
            if (!is_string($truncated)) {
                return '';
            }
        }

        return $truncated;
    }

    /**
     * @param array{onlineId: string|null, accountId: string|null, npCommunicationId: string|null} $identifiers
     * @return array{emit: bool, sampleRate: float, rateLimitPerMinute: int, samplingKey: string}
     */
    private static function evaluateMismatchEmission(
        string $service,
        string $operation,
        array $identifiers,
        ?string $correlationId,
        float $sampleRate,
        int $rateLimitPerMinute,
        ?string $rateLimitStorePath = null
    ): array {
        $samplingSubject = self::resolveSamplingSubject($identifiers, $correlationId);
        $samplingKey = sprintf('%s:%s:%s', $service, $operation, $samplingSubject);

        if (!self::passesSampling($samplingKey, $sampleRate)) {
            return [
                'emit' => false,
                'sampleRate' => $sampleRate,
                'rateLimitPerMinute' => $rateLimitPerMinute,
                'samplingKey' => $samplingKey,
            ];
        }

        $rateLimitKey = $service . ':' . $operation;
        if (!self::passesRateLimit($rateLimitKey, $rateLimitPerMinute, $rateLimitStorePath)) {
            return [
                'emit' => false,
                'sampleRate' => $sampleRate,
                'rateLimitPerMinute' => $rateLimitPerMinute,
                'samplingKey' => $samplingKey,
            ];
        }

        return [
            'emit' => true,
            'sampleRate' => $sampleRate,
            'rateLimitPerMinute' => $rateLimitPerMinute,
            'samplingKey' => $samplingKey,
        ];
    }

    /**
     * @param array{onlineId: string|null, accountId: string|null, npCommunicationId: string|null} $identifiers
     */
    private static function resolveSamplingSubject(array $identifiers, ?string $correlationId): string
    {
        if (is_string($identifiers['onlineId']) && $identifiers['onlineId'] !== '') {
            return 'onlineId:' . $identifiers['onlineId'];
        }

        if (is_string($identifiers['npCommunicationId']) && $identifiers['npCommunicationId'] !== '') {
            return 'npCommunicationId:' . $identifiers['npCommunicationId'];
        }

        if (is_string($identifiers['accountId']) && $identifiers['accountId'] !== '') {
            return 'accountId:' . $identifiers['accountId'];
        }

        if ($correlationId !== null && $correlationId !== '') {
            return 'correlationId:' . $correlationId;
        }

        return 'anonymous';
    }

    private static function passesSampling(string $samplingKey, float $sampleRate): bool
    {
        if ($sampleRate >= 1.0) {
            return true;
        }

        if ($sampleRate <= 0.0) {
            return false;
        }

        $hash = sprintf('%u', crc32($samplingKey));
        $normalized = ((int) $hash % 10_000) / 10_000;

        return $normalized <= $sampleRate;
    }

    private static function passesRateLimit(
        string $rateLimitKey,
        int $rateLimitPerMinute,
        ?string $rateLimitStorePath = null
    ): bool {
        $sharedStorePath = self::normalizeRateLimitStorePath($rateLimitStorePath);
        if ($sharedStorePath !== null) {
            $result = self::passesRateLimitWithSharedStore($sharedStorePath, $rateLimitKey, $rateLimitPerMinute);
            if ($result !== null) {
                return $result;
            }
        }

        $minuteWindow = (int) floor(time() / 60);
        $state = self::$mismatchRateLimitState[$rateLimitKey] ?? null;

        if ($state === null || $state['minuteWindow'] !== $minuteWindow) {
            self::$mismatchRateLimitState[$rateLimitKey] = [
                'minuteWindow' => $minuteWindow,
                'count' => 1,
            ];

            return true;
        }

        if ($state['count'] >= $rateLimitPerMinute) {
            return false;
        }

        self::$mismatchRateLimitState[$rateLimitKey]['count']++;

        return true;
    }

    private static function passesRateLimitWithSharedStore(
        string $storePath,
        string $rateLimitKey,
        int $rateLimitPerMinute
    ): ?bool {
        if (!self::isSafeStorePath($storePath)) {
            return null;
        }

        $storeHandle = @fopen($storePath, 'c+');
        if ($storeHandle === false) {
            return null;
        }

        if (!self::isOpenedFileSafe($storePath, $storeHandle)) {
            fclose($storeHandle);

            return null;
        }

        if (!flock($storeHandle, LOCK_EX)) {
            fclose($storeHandle);

            return null;
        }

        $minuteWindow = (int) floor(time() / 60);
        $stateKey = $minuteWindow . ':' . $rateLimitKey;

        rewind($storeHandle);
        $encodedState = stream_get_contents($storeHandle);
        $decodedState = is_string($encodedState) && $encodedState !== '' ? json_decode($encodedState, true) : [];
        $state = is_array($decodedState) ? $decodedState : [];

        $threshold = $minuteWindow - 1;
        foreach (array_keys($state) as $key) {
            if (!is_string($key)) {
                continue;
            }

            $delimiterOffset = strpos($key, ':');
            if ($delimiterOffset === false) {
                continue;
            }

            $window = (int) substr($key, 0, $delimiterOffset);
            if ($window < $threshold) {
                unset($state[$key]);
            }
        }

        $count = isset($state[$stateKey]) && is_int($state[$stateKey]) ? $state[$stateKey] : 0;
        if ($count >= $rateLimitPerMinute) {
            flock($storeHandle, LOCK_UN);
            fclose($storeHandle);

            return false;
        }

        $state[$stateKey] = $count + 1;

        rewind($storeHandle);
        ftruncate($storeHandle, 0);
        fwrite($storeHandle, (string) json_encode($state));
        fflush($storeHandle);
        flock($storeHandle, LOCK_UN);
        fclose($storeHandle);

        return true;
    }

    private static function normalizeRateLimitStorePath(?string $storePath): ?string
    {
        if ($storePath === null) {
            return null;
        }

        $trimmedPath = trim($storePath);
        if ($trimmedPath === '' || str_contains($trimmedPath, "\0")) {
            return null;
        }

        return $trimmedPath;
    }

    private static function ensurePrivateStoreDirectory(string $storeDir): bool
    {
        if (is_link($storeDir)) {
            return false;
        }

        if (!is_dir($storeDir) && !@mkdir($storeDir, 0700, true) && !is_dir($storeDir)) {
            return false;
        }

        $permissions = @fileperms($storeDir);
        if (is_int($permissions)) {
            $mode = $permissions & 0o777;
            if (($mode & 0o077) !== 0) {
                @chmod($storeDir, 0700);
            }
        }

        return is_dir($storeDir) && !is_link($storeDir);
    }

    private static function isSafeStorePath(string $storePath): bool
    {
        $directory = dirname($storePath);
        if ($directory === '' || $directory === '.') {
            return false;
        }

        if (is_link($directory) || is_link($storePath)) {
            return false;
        }

        if (!is_dir($directory) || !is_writable($directory)) {
            return false;
        }

        return true;
    }

    /**
     * @param resource $storeHandle
     */
    private static function isOpenedFileSafe(string $storePath, $storeHandle): bool
    {
        clearstatcache(true, $storePath);
        $pathStat = @lstat($storePath);
        $handleStat = @fstat($storeHandle);
        if (!is_array($pathStat) || !is_array($handleStat)) {
            return false;
        }

        if (($pathStat['mode'] & 0o170000) === 0o120000) {
            return false;
        }

        return isset($pathStat['ino'], $pathStat['dev'], $handleStat['ino'], $handleStat['dev'])
            && $pathStat['ino'] === $handleStat['ino']
            && $pathStat['dev'] === $handleStat['dev'];
    }

    private static function createCorrelationId(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (Throwable) {
            return (string) microtime(true);
        }
    }

    private static function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);

            return $stringValue === '' ? null : $stringValue;
        }

        return null;
    }

    private static function findFirstValueByKey(mixed $payload, string $targetKey): mixed
    {
        if (!is_array($payload)) {
            return null;
        }

        if (array_key_exists($targetKey, $payload)) {
            return $payload[$targetKey];
        }

        foreach ($payload as $value) {
            $found = self::findFirstValueByKey($value, $targetKey);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
