<?php

declare(strict_types=1);

final class TrophyAchiever
{
    private string $avatarUrl;

    private string $onlineId;

    private int $trophyCountNpwr;

    private int $trophyCountSony;

    private string $earnedDate;

    public function __construct(
        string $avatarUrl,
        string $onlineId,
        int $trophyCountNpwr,
        int $trophyCountSony,
        string $earnedDate
    ) {
        $this->avatarUrl = $avatarUrl;
        $this->onlineId = $onlineId;
        $this->trophyCountNpwr = $trophyCountNpwr;
        $this->trophyCountSony = $trophyCountSony;
        $this->earnedDate = $earnedDate;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['avatar_url'] ?? ''),
            (string) ($data['online_id'] ?? ''),
            (int) ($data['trophy_count_npwr'] ?? 0),
            (int) ($data['trophy_count_sony'] ?? 0),
            (string) ($data['earned_date'] ?? '')
        );
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getEarnedDate(): string
    {
        return $this->earnedDate;
    }

    public function hasHiddenTrophies(): bool
    {
        return $this->trophyCountNpwr < $this->trophyCountSony;
    }

    public function matchesOnlineId(?string $onlineId): bool
    {
        if ($onlineId === null) {
            return false;
        }

        return $this->onlineId === $onlineId;
    }
}
