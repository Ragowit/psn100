<?php

declare(strict_types=1);

require_once __DIR__ . '/GamePlayerFilter.php';
require_once __DIR__ . '/Utility.php';

readonly class GameLeaderboardRow
{
    private function __construct(
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
        private string $lastKnownDate
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['account_id']) ? (string) $row['account_id'] : '',
            (string) ($row['avatar_url'] ?? ''),
            (string) ($row['country'] ?? ''),
            (string) ($row['name'] ?? ''),
            isset($row['trophy_count_npwr']) ? (int) $row['trophy_count_npwr'] : 0,
            isset($row['trophy_count_sony']) ? (int) $row['trophy_count_sony'] : 0,
            isset($row['bronze']) ? (int) $row['bronze'] : 0,
            isset($row['silver']) ? (int) $row['silver'] : 0,
            isset($row['gold']) ? (int) $row['gold'] : 0,
            isset($row['platinum']) ? (int) $row['platinum'] : 0,
            isset($row['progress']) ? (int) $row['progress'] : 0,
            (string) ($row['last_known_date'] ?? '')
        );
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
