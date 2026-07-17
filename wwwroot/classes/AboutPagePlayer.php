<?php

declare(strict_types=1);

final readonly class AboutPagePlayer
{
    private const array STATUS_LABELS = [
        1 => 'Cheater',
        3 => 'Private',
        4 => 'Inactive',
    ];

    public function __construct(
        private Utility $utility,
        private string $onlineId,
        private string $countryCode,
        private string $avatarUrl,
        private ?string $lastUpdatedDate,
        private ?int $level,
        private ?string $progress,
        private int $rankLastWeek,
        private int $status,
        private int $trophyCountNpwr,
        private int $trophyCountSony,
        private ?int $ranking,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row, Utility $utility): self
    {
        return new self(
            $utility,
            (string) ($row['online_id'] ?? ''),
            isset($row['country']) ? (string) $row['country'] : '',
            (string) ($row['avatar_url'] ?? ''),
            isset($row['last_updated_date']) ? (string) $row['last_updated_date'] : null,
            isset($row['level']) ? (int) $row['level'] : null,
            isset($row['progress']) ? (string) $row['progress'] : null,
            isset($row['rank_last_week']) ? (int) $row['rank_last_week'] : 0,
            isset($row['status']) ? (int) $row['status'] : 0,
            isset($row['trophy_count_npwr']) ? (int) $row['trophy_count_npwr'] : 0,
            isset($row['trophy_count_sony']) ? (int) $row['trophy_count_sony'] : 0,
            isset($row['ranking']) ? (int) $row['ranking'] : null,
        );
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getCountryName(): string
    {
        return $this->utility->getCountryName($this->countryCode);
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }

    public function getLastUpdatedDate(): ?string
    {
        return $this->lastUpdatedDate;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function getProgress(): ?string
    {
        return $this->progress;
    }

    public function isRanked(): bool
    {
        return $this->status === 0 && $this->ranking !== null;
    }

    public function getRanking(): ?int
    {
        return $this->ranking;
    }

    public function hasHiddenTrophies(): bool
    {
        return $this->trophyCountNpwr < $this->trophyCountSony;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getStatusLabel(): ?string
    {
        return self::STATUS_LABELS[$this->status] ?? null;
    }

    public function isNew(): bool
    {
        return $this->rankLastWeek === 0 || $this->rankLastWeek === 16777215;
    }

    public function getRankDelta(): ?int
    {
        if (!$this->isRanked() || $this->isNew()) {
            return null;
        }

        return $this->rankLastWeek - (int) $this->ranking;
    }

    public function getRankDeltaColor(): ?string
    {
        $delta = $this->getRankDelta();

        if ($delta === null) {
            return null;
        }

        if ($delta < 0) {
            return '#d40b0b';
        }

        if ($delta > 0) {
            return '#0bd413';
        }

        return '#0070d1';
    }

    public function getRankDeltaLabel(): ?string
    {
        $delta = $this->getRankDelta();

        if ($delta === null) {
            return null;
        }

        if ($delta < 0) {
            return '(' . $delta . ')';
        }

        if ($delta > 0) {
            return '(+' . $delta . ')';
        }

        return '(=)';
    }
}
