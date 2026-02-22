<?php

declare(strict_types=1);

require_once __DIR__ . '/GamePlayerFilter.php';
require_once __DIR__ . '/Utility.php';

final readonly class GameRecentPlayer
{
    public function __construct(
        private string $accountId,
        private string $avatarUrl,
        private string $countryCode,
        private string $onlineId,
        private int $trophyCountNpwr,
        private int $trophyCountSony,
        private int $bronzeCount,
        private int $silverCount,
        private int $goldCount,
        private int $platinumCount,
        private int $progress,
        private string $lastKnownDate,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            accountId: (string) ($row['account_id'] ?? ''),
            avatarUrl: (string) ($row['avatar_url'] ?? ''),
            countryCode: (string) ($row['country'] ?? ''),
            onlineId: (string) ($row['name'] ?? ''),
            trophyCountNpwr: (int) ($row['trophy_count_npwr'] ?? 0),
            trophyCountSony: (int) ($row['trophy_count_sony'] ?? 0),
            bronzeCount: (int) ($row['bronze'] ?? 0),
            silverCount: (int) ($row['silver'] ?? 0),
            goldCount: (int) ($row['gold'] ?? 0),
            platinumCount: (int) ($row['platinum'] ?? 0),
            progress: (int) ($row['progress'] ?? 0),
            lastKnownDate: (string) ($row['last_known_date'] ?? ''),
        );
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function matchesAccountId(?string $accountId): bool
    {
        if ($accountId === null || $accountId === '') {
            return false;
        }

        return $this->accountId !== '' && $this->accountId === $accountId;
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getCountryName(Utility $utility): string
    {
        return $utility->getCountryName($this->countryCode);
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getBronzeCount(): int
    {
        return $this->bronzeCount;
    }

    public function getSilverCount(): int
    {
        return $this->silverCount;
    }

    public function getGoldCount(): int
    {
        return $this->goldCount;
    }

    public function getPlatinumCount(): int
    {
        return $this->platinumCount;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getLastKnownDate(): string
    {
        return $this->lastKnownDate;
    }

    public function hasHiddenTrophies(): bool
    {
        return $this->trophyCountNpwr < $this->trophyCountSony;
    }

    /**
     * @return array<string, string>
     */
    public function getAvatarQueryParameters(GamePlayerFilter $filter): array
    {
        return $filter->withAvatar($this->avatarUrl);
    }

    /**
     * @return array<string, string>
     */
    public function getCountryQueryParameters(GamePlayerFilter $filter): array
    {
        return $filter->withCountry($this->countryCode);
    }
}
