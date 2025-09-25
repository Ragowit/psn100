<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntry.php';
require_once __DIR__ . '/Utility.php';

class ChangelogEntryPresenter
{
    private ChangelogEntry $entry;
    private Utility $utility;

    public function __construct(ChangelogEntry $entry, Utility $utility)
    {
        $this->entry = $entry;
        $this->utility = $utility;
    }

    public function getDateLabel(): string
    {
        return $this->entry->getTime()->format('Y-m-d');
    }

    public function getTimeLabel(): string
    {
        return $this->entry->getTime()->format('H:i:s');
    }

    public function getMessage(): string
    {
        $param1Link = $this->buildGameLink($this->entry->getParam1Id(), $this->entry->getParam1Name());
        $param1RegionBadge = $this->buildRegionBadge($this->entry->getParam1Region());
        $param1PlatformBadges = $this->buildPlatformBadges($this->entry->getParam1Platforms());

        $param2Link = $this->buildGameLink($this->entry->getParam2Id(), $this->entry->getParam2Name());
        $param2PlatformBadges = $this->buildPlatformBadges($this->entry->getParam2Platforms());

        $extra = $this->escape($this->entry->getExtra());

        switch ($this->entry->getChangeType()) {
            case ChangelogEntry::TYPE_GAME_CLONE:
                return sprintf(
                    '%s was cloned: %s',
                    $this->formatGameReference($param1Link, '', $param1PlatformBadges),
                    $this->formatGameReference($param2Link, '', $param2PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_COPY:
                return sprintf(
                    'Copied trophy data from %s into %s.',
                    $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges),
                    $this->formatGameReference($param2Link, '', $param2PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_DELETE:
                return sprintf(
                    "The merged game entry for '%s' have been deleted.",
                    $extra
                );
            case ChangelogEntry::TYPE_GAME_DELISTED:
                return sprintf(
                    '%s status was set to delisted.',
                    $this->formatGameReference($param1Link, '', $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_DELISTED_AND_OBSOLETE:
                return sprintf(
                    '%s status was set to delisted &amp; obsolete.',
                    $this->formatGameReference($param1Link, '', $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_MERGE:
                return sprintf(
                    '%s was merged into %s',
                    $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges),
                    $this->formatGameReference($param2Link, '', $param2PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_NORMAL:
                return sprintf(
                    '%s status was set to normal.',
                    $this->formatGameReference($param1Link, '', $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_OBSOLETE:
                return sprintf(
                    '%s status was set to obsolete.',
                    $this->formatGameReference($param1Link, '', $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_OBTAINABLE:
                return sprintf(
                    'Trophies have been tagged as obtainable for %s.',
                    $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_RESCAN:
                return sprintf(
                    'The game %s have been rescanned for updated/new trophy data and game details.',
                    $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_RESET:
                return sprintf(
                    'Merged trophies have been reset for %s.',
                    $this->formatGameReference($param1Link, '', $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_UNOBTAINABLE:
                return sprintf(
                    'Trophies have been tagged as unobtainable for %s.',
                    $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_UPDATE:
                return sprintf(
                    '%s was updated.',
                    $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
                );
            case ChangelogEntry::TYPE_GAME_VERSION:
                return sprintf(
                    '%s has a new version.',
                    $this->formatGameReference($param1Link, $param1RegionBadge, $param1PlatformBadges)
                );
            default:
                return sprintf(
                    'Unknown type: %s',
                    $this->escape($this->entry->getChangeType())
                );
        }
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
