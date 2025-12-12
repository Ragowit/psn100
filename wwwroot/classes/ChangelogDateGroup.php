<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogEntryPresenter.php';

readonly class ChangelogDateGroup
{
    /**
     * @var ChangelogEntryPresenter[]
     */
    private array $entries;

    /**
     * @param ChangelogEntryPresenter[] $entries
     */
    public function __construct(
        private string $dateLabel,
        array $entries
    ) {
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
