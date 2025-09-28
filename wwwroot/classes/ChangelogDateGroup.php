<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntryPresenter.php';

class ChangelogDateGroup
{
    private string $dateLabel;

    /**
     * @var ChangelogEntryPresenter[]
     */
    private array $entries;

    /**
     * @param ChangelogEntryPresenter[] $entries
     */
    public function __construct(string $dateLabel, array $entries)
    {
        $this->dateLabel = $dateLabel;
        $this->entries = array_values($entries);
    }

    public function getDateLabel(): string
    {
        return $this->dateLabel;
    }

    /**
     * @return ChangelogEntryPresenter[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}
