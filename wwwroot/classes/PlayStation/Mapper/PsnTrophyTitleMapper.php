<?php

declare(strict_types=1);

require_once __DIR__ . '/../Dto/PsnTrophyTitleDto.php';

final class PsnTrophyTitleMapper
{
    public function mapFromApiTitle(object $trophyTitle): PsnTrophyTitleDto
    {
        $platforms = $trophyTitle->platforms();
        if ($platforms instanceof Traversable) {
            $platforms = iterator_to_array($platforms, false);
        }

        if (!is_array($platforms)) {
            $platforms = [];
        }

        $normalizedPlatforms = [];
        foreach ($platforms as $platform) {
            if (!is_string($platform) || $platform === '') {
                continue;
            }

            $normalizedPlatforms[] = $platform;
        }

        return new PsnTrophyTitleDto(
            (string) $trophyTitle->npCommunicationId(),
            (string) $trophyTitle->name(),
            (string) $trophyTitle->detail(),
            (string) $trophyTitle->iconUrl(),
            (string) $trophyTitle->lastUpdatedDateTime(),
            (string) $trophyTitle->trophySetVersion(),
            $normalizedPlatforms
        );
    }
}
