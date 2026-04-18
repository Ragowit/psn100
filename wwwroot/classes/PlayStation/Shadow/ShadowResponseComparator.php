<?php

declare(strict_types=1);

final class ShadowResponseComparator
{
    /**
     * @return array{hasMismatch: bool, mismatches: list<array{path: string, legacy: mixed, shadow: mixed, type: string}>}
     */
    public static function compare(mixed $legacy, mixed $shadow): array
    {
        $mismatches = [];
        self::walk('', $legacy, $shadow, $mismatches);

        return [
            'hasMismatch' => $mismatches !== [],
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @param list<array{path: string, legacy: mixed, shadow: mixed, type: string}> $mismatches
     */
    private static function walk(string $path, mixed $legacy, mixed $shadow, array &$mismatches): void
    {
        if (gettype($legacy) !== gettype($shadow)) {
            $mismatches[] = [
                'path' => $path,
                'legacy' => $legacy,
                'shadow' => $shadow,
                'type' => 'type_mismatch',
            ];

            return;
        }

        if (is_array($legacy) && is_array($shadow)) {
            $keys = array_values(array_unique(array_merge(array_keys($legacy), array_keys($shadow))));

            foreach ($keys as $key) {
                $nextPath = $path === '' ? (string) $key : sprintf('%s.%s', $path, (string) $key);
                $legacyHasKey = array_key_exists($key, $legacy);
                $shadowHasKey = array_key_exists($key, $shadow);

                if (!$legacyHasKey || !$shadowHasKey) {
                    $mismatches[] = [
                        'path' => $nextPath,
                        'legacy' => $legacyHasKey ? $legacy[$key] : null,
                        'shadow' => $shadowHasKey ? $shadow[$key] : null,
                        'type' => 'missing_field',
                    ];

                    continue;
                }

                self::walk($nextPath, $legacy[$key], $shadow[$key], $mismatches);
            }

            return;
        }

        if ($legacy !== $shadow) {
            $mismatches[] = [
                'path' => $path,
                'legacy' => $legacy,
                'shadow' => $shadow,
                'type' => 'value_mismatch',
            ];
        }
    }
}
