<?php

declare(strict_types=1);

/**
 * Filesystem locations for cached PSN trophy artwork.
 *
 * Centralises the paths that were previously duplicated across cron and admin
 * rescan code so future environment-based configuration only needs one place.
 */
final readonly class TrophyImageDirectories
{
    public function __construct(
        final public string $title,
        final public string $group,
        final public string $trophy,
        final public string $reward,
    ) {
    }

    #[\NoDiscard]
    public static function productionDefault(): self
    {
        return new self(
            '/home/psn100/public_html/img/title/',
            '/home/psn100/public_html/img/group/',
            '/home/psn100/public_html/img/trophy/',
            '/home/psn100/public_html/img/reward/',
        );
    }
}
