<?php

declare(strict_types=1);

final class NpServiceNamePolicy
{
    /**
     * @return list<array{npServiceName?: string}>
     */
    public function buildLookupQueryVariants(?string $preferredNpServiceName): array
    {
        $queryVariants = [];
        $addVariant = static function (array $variant) use (&$queryVariants): void {
            if (!in_array($variant, $queryVariants, true)) {
                $queryVariants[] = $variant;
            }
        };

        if ($preferredNpServiceName === 'trophy' || $preferredNpServiceName === 'trophy2') {
            $addVariant(['npServiceName' => $preferredNpServiceName]);
        } else {
            $addVariant([]);
        }

        $addVariant(['npServiceName' => 'trophy']);
        $addVariant(['npServiceName' => 'trophy2']);
        $addVariant([]);

        return $queryVariants;
    }

    /**
     * @param list<array{npServiceName?: string}> $queryVariants
     * @param array{npServiceName?: string} $winningQuery
     * @return array{npServiceName?: string}|null
     */
    public function resolveAlternateQueryVariant(array $queryVariants, array $winningQuery): ?array
    {
        foreach ($queryVariants as $queryVariant) {
            if ($queryVariant !== $winningQuery) {
                return $queryVariant;
            }
        }

        return null;
    }

    public function resolvePreferredNpServiceName(?string $platform): ?string
    {
        if ($platform === null) {
            return null;
        }

        $platforms = array_values(array_filter(array_map(
            static fn (string $value): string => strtoupper(trim($value)),
            explode(',', $platform)
        ), static fn (string $value): bool => $value !== ''));

        if ($platforms === []) {
            return null;
        }

        $legacyPlatforms = ['PS3', 'PS4', 'PSVR', 'PSVITA'];
        foreach ($platforms as $platformValue) {
            if (in_array($platformValue, $legacyPlatforms, true)) {
                return 'trophy';
            }
        }

        return 'trophy2';
    }
}
