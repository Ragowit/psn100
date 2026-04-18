<?php

declare(strict_types=1);

require_once __DIR__ . '/ShadowResponseComparator.php';

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
        int $shadowLatencyBudgetMs = 350
    ): mixed {
        $legacyStart = hrtime(true);
        $legacyResponse = $legacyExecutor();
        $legacyDurationMs = (int) ((hrtime(true) - $legacyStart) / 1_000_000);

        if (!$mode->isShadow()) {
            return $legacyResponse;
        }

        if ($legacyDurationMs >= $shadowLatencyBudgetMs) {
            self::emitEvent([
                'event' => 'psn_shadow_skipped',
                'operation' => $operation,
                'reason' => 'legacy_latency_budget_exhausted',
                'legacyDurationMs' => $legacyDurationMs,
                'shadowLatencyBudgetMs' => $shadowLatencyBudgetMs,
            ]);

            return $legacyResponse;
        }

        $shadowStart = hrtime(true);
        try {
            $shadowResponse = $shadowExecutor();
            $shadowDurationMs = (int) ((hrtime(true) - $shadowStart) / 1_000_000);

            if ($shadowDurationMs > $shadowLatencyBudgetMs) {
                self::emitEvent([
                    'event' => 'psn_shadow_sla_warning',
                    'operation' => $operation,
                    'legacyDurationMs' => $legacyDurationMs,
                    'shadowDurationMs' => $shadowDurationMs,
                    'shadowLatencyBudgetMs' => $shadowLatencyBudgetMs,
                ]);
            }

            $comparison = ShadowResponseComparator::compare(
                $normalizer($legacyResponse),
                $normalizer($shadowResponse)
            );

            if ($comparison['hasMismatch']) {
                self::emitEvent([
                    'event' => 'psn_shadow_mismatch',
                    'operation' => $operation,
                    'legacyDurationMs' => $legacyDurationMs,
                    'shadowDurationMs' => $shadowDurationMs,
                    'mismatch' => $comparison,
                ]);
            }
        } catch (Throwable $shadowException) {
            self::emitEvent([
                'event' => 'psn_shadow_failure',
                'operation' => $operation,
                'legacyDurationMs' => $legacyDurationMs,
                'errorType' => $shadowException::class,
                'message' => $shadowException->getMessage(),
            ]);
        }

        return $legacyResponse;
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
