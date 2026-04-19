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
    private const float DEFAULT_MISMATCH_SAMPLE_RATE = 0.2;
    private const int DEFAULT_MISMATCH_RATE_LIMIT_PER_MINUTE = 60;
    private const int MAX_MISMATCH_SAMPLES = 3;

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

            $normalizedLegacyResponse = $normalizer($legacyResponse);
            $normalizedShadowResponse = $normalizer($shadowResponse);
            $comparison = ShadowResponseComparator::compare(
                $normalizedLegacyResponse,
                $normalizedShadowResponse
            );

            if ($comparison['hasMismatch']) {
                $service = self::toNullableString($metricTags['service'] ?? null) ?? 'unknown_service';
                $correlationId = self::toNullableString($metricTags['correlationId'] ?? null)
                    ?? self::toNullableString($metricTags['requestId'] ?? null)
                    ?? self::createCorrelationId();
                $identifiers = self::buildIdentifiers($metricTags, $normalizedLegacyResponse, $normalizedShadowResponse);

                $rateLimitDecision = self::evaluateMismatchEmission(
                    $service,
                    $operation,
                    $correlationId,
                    self::resolveMismatchSampleRate($metricTags),
                    self::resolveMismatchRateLimitPerMinute($metricTags)
                );

                if ($rateLimitDecision['emit']) {
                    self::emitEvent(array_merge($metricTags, [
                        'event' => 'psn_shadow_mismatch',
                        'service' => $service,
                        'operation' => $operation,
                        'timestamp' => gmdate('c'),
                        'correlationId' => $correlationId,
                        'requestId' => $correlationId,
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
    }

    private static function executeShadowWithTimeout(callable $shadowExecutor, int $shadowLatencyBudgetMs): mixed
    {
        if (
            !function_exists('pcntl_signal')
            || !function_exists('pcntl_async_signals')
            || !function_exists('pcntl_setitimer')
        ) {
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
                return substr($value, 0, 125) . '...';
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
     * @return array{emit: bool, sampleRate: float, rateLimitPerMinute: int, samplingKey: string}
     */
    private static function evaluateMismatchEmission(
        string $service,
        string $operation,
        string $correlationId,
        float $sampleRate,
        int $rateLimitPerMinute
    ): array {
        $samplingKey = sprintf('%s:%s:%s', $service, $operation, $correlationId);

        if (!self::passesSampling($samplingKey, $sampleRate)) {
            return [
                'emit' => false,
                'sampleRate' => $sampleRate,
                'rateLimitPerMinute' => $rateLimitPerMinute,
                'samplingKey' => $samplingKey,
            ];
        }

        $rateLimitKey = $service . ':' . $operation;
        if (!self::passesRateLimit($rateLimitKey, $rateLimitPerMinute)) {
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

    private static function passesRateLimit(string $rateLimitKey, int $rateLimitPerMinute): bool
    {
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
