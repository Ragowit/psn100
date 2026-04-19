<?php

declare(strict_types=1);

final class ShadowPromotionPolicy
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private readonly array $configuration)
    {
    }

    /**
     * @param array<string, array<string, int|float>> $windowMetrics
     * @return array{promote: bool, checks: list<array{window: string, metric: string, passed: bool, actual: float|int, threshold: float|int, comparator: string}>, reasons: list<string>}
     */
    public function evaluate(string $service, string $operation, array $windowMetrics): array
    {
        $thresholds = $this->resolveThresholds($service, $operation);
        $checks = [];
        $reasons = [];

        foreach ($thresholds as $metric => $windowThresholds) {
            if (!is_array($windowThresholds)) {
                continue;
            }

            foreach ($windowThresholds as $window => $thresholdValue) {
                if (!is_string($window) || !is_numeric($thresholdValue)) {
                    continue;
                }

                $metrics = $windowMetrics[$window] ?? [];
                if (!is_array($metrics)) {
                    $metrics = [];
                }

                $actualValue = $this->measureMetric($metric, $metrics);
                [$passed, $comparator] = $this->evaluateThreshold($metric, $actualValue, (float) $thresholdValue);

                $checks[] = [
                    'window' => $window,
                    'metric' => $metric,
                    'passed' => $passed,
                    'actual' => $actualValue,
                    'threshold' => is_int($thresholdValue) ? (int) $thresholdValue : (float) $thresholdValue,
                    'comparator' => $comparator,
                ];

                if (!$passed) {
                    $reasons[] = sprintf(
                        '%s failed for %s/%s in %s (actual=%s threshold=%s)',
                        $metric,
                        $service,
                        $operation,
                        $window,
                        (string) $actualValue,
                        (string) $thresholdValue
                    );
                }
            }
        }

        return [
            'promote' => $reasons === [],
            'checks' => $checks,
            'reasons' => array_values(array_unique($reasons)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveThresholds(string $service, string $operation): array
    {
        $thresholds = $this->configuration['thresholds'] ?? [];
        if (!is_array($thresholds)) {
            return [];
        }

        $resolved = [];
        if (isset($thresholds['default']) && is_array($thresholds['default'])) {
            $resolved = $thresholds['default'];
        }

        $serviceThresholds = $thresholds['services'][$service] ?? null;
        if (is_array($serviceThresholds)) {
            $resolved = array_replace_recursive($resolved, $serviceThresholds);

            $operationThresholds = $serviceThresholds['operations'][$operation] ?? null;
            if (is_array($operationThresholds)) {
                $resolved = array_replace_recursive($resolved, $operationThresholds);
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, int|float> $metrics
     */
    private function measureMetric(string $metric, array $metrics): float|int
    {
        $totalCompared = max(0, (int) ($metrics['totalCompared'] ?? 0));
        $mismatched = max(0, (int) ($metrics['mismatched'] ?? 0));
        $newClientErrors = max(0, (int) ($metrics['newClientErrors'] ?? 0));
        $normalizationSkips = max(0, (int) ($metrics['skippedNormalizationFailure'] ?? 0));

        return match ($metric) {
            'minCompared' => $totalCompared,
            'maxMismatchRate' => $totalCompared === 0 ? 1.0 : $mismatched / $totalCompared,
            'maxNewClientErrorRate' => $totalCompared === 0 ? 1.0 : $newClientErrors / $totalCompared,
            'maxNormalizationSkipRate' => $totalCompared === 0 ? 1.0 : $normalizationSkips / $totalCompared,
            default => 1.0,
        };
    }

    /**
     * @return array{bool, string}
     */
    private function evaluateThreshold(string $metric, float|int $actual, float $threshold): array
    {
        if ($metric === 'minCompared') {
            return [$actual >= $threshold, '>='];
        }

        return [$actual <= $threshold, '<='];
    }
}
