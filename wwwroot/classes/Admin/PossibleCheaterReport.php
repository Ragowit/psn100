<?php

declare(strict_types=1);

require_once __DIR__ . '/../PlayerUrlBuilder.php';
require_once __DIR__ . '/../Utility.php';

final class PossibleCheaterReport
{
    /**
     * @param PossibleCheaterReportEntry[] $generalCheaters
     * @param PossibleCheaterReportSection[] $sections
     */
    public function __construct(
        final private array $generalCheaters,
        final private array $sections
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

final class PossibleCheaterReportEntry
{
    public function __construct(
        final private int $gameId,
        final private string $gameName,
        final private string $playerName,
        final private int $accountId
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
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
        $slug = $this->gameId . '-' . $utility->slugify($this->gameName);

        return PlayerUrlBuilder::gamePlayerPath($slug, $this->playerName);
    }
}

final class PossibleCheaterReportSection
{
    /**
     * @param PossibleCheaterReportSectionEntry[] $entries
     */
    public function __construct(
        final private string $title,
        final private array $entries
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
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

final class PossibleCheaterReportSectionEntry
{
    public function __construct(
        final private string $url,
        final private string $onlineId,
        final private int $accountId
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
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
