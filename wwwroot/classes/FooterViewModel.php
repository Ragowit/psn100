<?php

declare(strict_types=1);

final readonly class FooterViewModel
{
    public function __construct(
        private int $startYear,
        private int $currentYear,
        private string $versionLabel,
        private string $releaseUrl,
        private string $changelogUrl,
        private string $issuesUrl,
        private string $creatorName,
        private string $creatorProfileUrl,
        private string $contributorsUrl
    ) {
    }

    public static function createDefault(): self
    {
        $currentYear = (int) date('Y');

        return new self(
            2019,
            $currentYear,
            'v7.46',
            'https://github.com/Ragowit/psn100/releases',
            '/changelog',
            'https://github.com/Ragowit/psn100/issues',
            'Ragowit',
            '/player/Ragowit',
            'https://github.com/ragowit/psn100/graphs/contributors'
        );
    }

    public function getYearRangeLabel(): string
    {
        if ($this->startYear >= $this->currentYear) {
            return (string) $this->startYear;
        }

        return $this->startYear . '-' . $this->currentYear;
    }

    public function getVersionLabel(): string
    {
        return $this->versionLabel;
    }

    public function getReleaseUrl(): string
    {
        return $this->releaseUrl;
    }

    public function getChangelogUrl(): string
    {
        return $this->changelogUrl;
    }

    public function getIssuesUrl(): string
    {
        return $this->issuesUrl;
    }

    public function getCreatorName(): string
    {
        return $this->creatorName;
    }

    public function getCreatorProfileUrl(): string
    {
        return $this->creatorProfileUrl;
    }

    public function getContributorsUrl(): string
    {
        return $this->contributorsUrl;
    }
}
