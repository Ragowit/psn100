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
    /**
     * @template T
     * @param callable(): T $legacyExecutor
     * @param callable(): mixed $shadowExecutor
     * @param callable(mixed): array<string, mixed> $normalizer
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

            $comparison = ShadowResponseComparator::compare(
                $normalizer($legacyResponse),
                $normalizer($shadowResponse)
            );

            if ($comparison['hasMismatch']) {
                self::emitEvent(array_merge($metricTags, [
                    'event' => 'psn_shadow_mismatch',
                    'operation' => $operation,
                    'legacyDurationMs' => $legacyDurationMs,
                    'shadowDurationMs' => $shadowDurationMs,
                    'mismatch' => $comparison,
                ]));
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
        $encoded = json_encode($payload);

        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        error_log($encoded);
    }
}
