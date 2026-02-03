<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntry.php';
require_once __DIR__ . '/Utility.php';

readonly class ChangelogEntryPresenter
{
    public function __construct(
        private ChangelogEntry $entry,
        private Utility $utility
    ) {}

    public function getDateLabel(): string
    {
        return $this->entry->getTime()->format('Y-m-d');
    }

    public function getTimeLabel(): string
    {
        return $this->entry->getTime()->format('H:i:s');
    }

    public function getIsoTimestamp(): string
    {
        return $this->entry->getTime()->format(\DateTimeInterface::ATOM);
    }

    public function getMessage(): string
    {
        $param1Link = $this->buildGameLink($this->entry->getParam1Id(), $this->entry->getParam1Name());
        $param1RegionBadge = $this->buildRegionBadge($this->entry->getParam1Region());
        $param1PlatformBadges = $this->buildPlatformBadges($this->entry->getParam1Platforms());

        $param2Link = $this->buildGameLink($this->entry->getParam2Id(), $this->entry->getParam2Name());
        $param2PlatformBadges = $this->buildPlatformBadges($this->entry->getParam2Platforms());

        $extra = $this->escape($this->entry->getExtra());

        return match ($this->entry->getChangeType()) {
            ChangelogEntryType::GAME_CLONE => sprintf(
                '%s was cloned: %s',
                $this->formatGameReference($param1Link, '', $param1PlatformBadges),
                $this->formatGameReference($param2Link, '', $param2PlatformBadges)
            ),
            ChangelogEntryType::GAME_COPY => sprintf(
                'Copied trophy data from %s into %s.',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges),
                $this->formatGameReference($param2Link, '', $param2PlatformBadges)
            ),
            ChangelogEntryType::GAME_DELETE => sprintf(
                "The merged game entry for '%s' have been deleted.",
                $extra
            ),
            ChangelogEntryType::GAME_DELISTED => sprintf(
                '%s status was set to delisted.',
                $this->formatGameReference($param1Link, '', $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_DELISTED_AND_OBSOLETE => sprintf(
                '%s status was set to delisted &amp; obsolete.',
                $this->formatGameReference($param1Link, '', $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_HISTORY_SNAPSHOT => sprintf(
                'A new trophy history snapshot was recorded for %s.',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_MERGE => sprintf(
                '%s was merged into %s',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges),
                $this->formatGameReference($param2Link, '', $param2PlatformBadges)
            ),
            ChangelogEntryType::GAME_NORMAL => sprintf(
                '%s status was set to normal.',
                $this->formatGameReference($param1Link, '', $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_OBSOLETE => sprintf(
                '%s status was set to obsolete.',
                $this->formatGameReference($param1Link, '', $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_OBTAINABLE => sprintf(
                'Trophies have been tagged as obtainable for %s.',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_RESCAN => sprintf(
                'The game %s have been rescanned for updated/new trophy data and game details.',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_RESET => sprintf(
                'Merged trophies have been reset for %s.',
                $this->formatGameReference($param1Link, '', $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_UNOBTAINABLE => sprintf(
                'Trophies have been tagged as unobtainable for %s.',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_UPDATE => sprintf(
                '%s was updated.',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
            ),
            ChangelogEntryType::GAME_VERSION => sprintf(
                '%s has a new version.',
                $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
            ),
            default => sprintf(
                'Unknown type: %s',
                $this->escape($this->entry->getChangeTypeValue())
            ),
        };
    }

    /**
     * @param array<int, string> $platforms
     */
    private function buildPlatformBadges(array $platforms): string
    {
        if ($platforms === []) {
            return '';
        }

        $badges = array_map(
            static fn(string $platform): string => sprintf(
                '<span class="badge rounded-pill text-bg-primary">%s</span>',
                htmlentities($platform, ENT_QUOTES, 'UTF-8')
            ),
            $platforms
        );

        return implode(' ', $badges);
    }

    private function buildRegionBadge(?string $region): string
    {
        if ($region === null || $region === '') {
            return '';
        }

        return sprintf(
            ' <span class="badge rounded-pill text-bg-primary">%s</span>',
            htmlentities($region, ENT_QUOTES, 'UTF-8')
        );
    }

    private function buildGameLink(?int $id, ?string $name): string
    {
        $idPart = $id !== null ? (string) $id : '';
        $name = $name ?? '';
        $url = '/game/' . $idPart . '-' . $this->utility->slugify($name);

        return sprintf(
            '<a href="%s">%s</a>',
            htmlentities($url, ENT_QUOTES, 'UTF-8'),
            htmlentities($name, ENT_QUOTES, 'UTF-8')
        );
    }

    private function formatGameReference(string $link, string $regionBadge, string $platformBadges): string
    {
        return sprintf(
            '%s%s %s',
            $link,
            $regionBadge,
            $this->wrapWithParentheses($platformBadges)
        );
    }

    private function wrapWithParentheses(string $content): string
    {
        return sprintf('(%s)', $content);
    }

    private function escape(?string $value): string
    {
        return htmlentities($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}
