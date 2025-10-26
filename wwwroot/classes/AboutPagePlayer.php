<?php

declare(strict_types=1);

class AboutPagePlayer
{
    private const STATUS_LABELS = [
        1 => 'Cheater',
        3 => 'Private',
        4 => 'Inactive',
    ];

    private Utility $utility;
    private string $onlineId;
    private string $countryCode;
    private string $avatarUrl;
    private ?string $lastUpdatedDate;
    private ?int $level;
    private ?string $progress;
    private int $rankLastWeek;
    private int $status;
    private int $trophyCountNpwr;
    private int $trophyCountSony;
    private ?int $ranking;

    public function __construct(
        Utility $utility,
        string $onlineId,
        ?string $countryCode,
        string $avatarUrl,
        ?string $lastUpdatedDate,
        ?int $level,
        ?string $progress,
        ?int $rankLastWeek,
        ?int $status,
        ?int $trophyCountNpwr,
        ?int $trophyCountSony,
        ?int $ranking
    ) {
        $this->utility = $utility;
        $this->onlineId = $onlineId;
        $this->countryCode = $countryCode ?? '';
        $this->avatarUrl = $avatarUrl;
        $this->lastUpdatedDate = $lastUpdatedDate;
        $this->level = $level;
        $this->progress = $progress;
        $this->rankLastWeek = $rankLastWeek ?? 0;
        $this->status = $status ?? 0;
        $this->trophyCountNpwr = $trophyCountNpwr ?? 0;
        $this->trophyCountSony = $trophyCountSony ?? 0;
        $this->ranking = $ranking;
    }

    public static function fromArray(array $row, Utility $utility): self
    {
        return new self(
            $utility,
            (string) ($row['online_id'] ?? ''),
            isset($row['country']) ? (string) $row['country'] : null,
            (string) ($row['avatar_url'] ?? ''),
            isset($row['last_updated_date']) ? (string) $row['last_updated_date'] : null,
            isset($row['level']) ? (int) $row['level'] : null,
            isset($row['progress']) ? (string) $row['progress'] : null,
            isset($row['rank_last_week']) ? (int) $row['rank_last_week'] : null,
            isset($row['status']) ? (int) $row['status'] : null,
            isset($row['trophy_count_npwr']) ? (int) $row['trophy_count_npwr'] : null,
            isset($row['trophy_count_sony']) ? (int) $row['trophy_count_sony'] : null,
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
