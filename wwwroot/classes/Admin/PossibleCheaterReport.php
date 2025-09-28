<?php

declare(strict_types=1);

require_once __DIR__ . '/../Utility.php';

class PossibleCheaterReport
{
    /**
     * @param PossibleCheaterReportEntry[] $generalCheaters
     * @param PossibleCheaterReportSection[] $sections
     */
    public function __construct(
        private array $generalCheaters,
        private array $sections
    ) {
    }

    /**
     * @return PossibleCheaterReportEntry[]
     */
    public function getGeneralCheaters(): array
    {
        return $this->generalCheaters;
    }

    /**
     * @return PossibleCheaterReportSection[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }
}

class PossibleCheaterReportEntry
{
    public function __construct(
        private int $gameId,
        private string $gameName,
        private string $playerName,
        private int $accountId
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['game_id'] ?? 0),
            (string) ($data['game_name'] ?? ''),
            (string) ($data['player_name'] ?? ''),
            (int) ($data['account_id'] ?? 0)
        );
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
    }

    public function getProfileUrl(Utility $utility): string
    {
        $slug = $utility->slugify($this->gameName);
        $player = rawurlencode($this->playerName);

        return sprintf('/game/%d-%s/%s', $this->gameId, $slug, $player);
    }
}

class PossibleCheaterReportSection
{
    /**
     * @param PossibleCheaterReportSectionEntry[] $entries
     */
    public function __construct(
        private string $title,
        private array $entries
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $title = (string) ($data['title'] ?? '');
        $entryData = is_array($data['entries'] ?? null) ? $data['entries'] : [];

        $entries = array_map(
            static fn(array $entry): PossibleCheaterReportSectionEntry => PossibleCheaterReportSectionEntry::fromArray($entry),
            $entryData
        );

        return new self($title, $entries);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return PossibleCheaterReportSectionEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }
}

class PossibleCheaterReportSectionEntry
{
    public function __construct(
        private string $url,
        private string $onlineId,
        private int $accountId
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['url'] ?? ''),
            (string) ($data['online_id'] ?? ''),
            (int) ($data['account_id'] ?? 0)
        );
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
    }
}
