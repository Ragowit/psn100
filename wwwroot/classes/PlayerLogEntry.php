<?php

declare(strict_types=1);

final readonly class PlayerLogEntry
{
    public function __construct(
        private int $trophyId,
        private string $trophyType,
        private string $trophyName,
        private string $trophyDetail,
        private string $trophyIcon,
        private ?string $rarityPercent,
        private ?string $inGameRarityPercent,
        private int $trophyStatus,
        private ?int $progressTargetValue,
        private ?int $progress,
        private bool $isEarned,
        private ?string $rewardName,
        private ?string $rewardImageUrl,
        private int $gameId,
        private string $gameName,
        private int $gameStatus,
        private string $gameIcon,
        private string $platforms,
        private string $earnedDate
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            trophyId: isset($row['trophy_id']) ? (int) $row['trophy_id'] : 0,
            trophyType: (string) ($row['trophy_type'] ?? ''),
            trophyName: (string) ($row['trophy_name'] ?? ''),
            trophyDetail: (string) ($row['trophy_detail'] ?? ''),
            trophyIcon: (string) ($row['trophy_icon'] ?? ''),
            rarityPercent: self::toNullableString($row['rarity_percent'] ?? null),
            inGameRarityPercent: self::toNullableString($row['in_game_rarity_percent'] ?? null),
            trophyStatus: isset($row['trophy_status']) ? (int) $row['trophy_status'] : 0,
            progressTargetValue: self::toNullableInt($row['progress_target_value'] ?? null),
            progress: self::toNullableInt($row['progress'] ?? null),
            isEarned: isset($row['earned']) ? ((int) $row['earned'] === 1) : false,
            rewardName: self::toNullableString($row['reward_name'] ?? null),
            rewardImageUrl: self::toNullableString($row['reward_image_url'] ?? null),
            gameId: isset($row['game_id']) ? (int) $row['game_id'] : 0,
            gameName: (string) ($row['game_name'] ?? ''),
            gameStatus: isset($row['game_status']) ? (int) $row['game_status'] : 0,
            gameIcon: (string) ($row['game_icon'] ?? ''),
            platforms: (string) ($row['platform'] ?? ''),
            earnedDate: (string) ($row['earned_date'] ?? '')
        );
    }

    public function getTrophyId(): int
    {
        return $this->trophyId;
    }

    public function getTrophyType(): string
    {
        return $this->trophyType;
    }

    public function getTrophyName(): string
    {
        return $this->trophyName;
    }

    public function getTrophyDetail(): string
    {
        return $this->trophyDetail;
    }

    public function getTrophyIcon(): string
    {
        return $this->trophyIcon;
    }

    public function getTrophyIconRelativePath(): string
    {
        if ($this->trophyIcon === '.png') {
            return $this->usesPs5Assets()
                ? '../missing-ps5-game-and-trophy.png'
                : '../missing-ps4-trophy.png';
        }

        return $this->trophyIcon;
    }

    public function getRarityPercent(): ?string
    {
        return $this->rarityPercent;
    }

    public function getInGameRarityPercent(): ?string
    {
        return $this->inGameRarityPercent;
    }

    public function getTrophyStatus(): int
    {
        return $this->trophyStatus;
    }

    public function getProgressTargetValue(): ?int
    {
        return $this->progressTargetValue;
    }

    public function getProgress(): ?int
    {
        return $this->progress;
    }

    public function getProgressDisplay(): ?string
    {
        if ($this->progressTargetValue === null) {
            return null;
        }

        $progress = $this->progress ?? 0;

        if ($this->isEarned) {
            $progress = $this->progressTargetValue;
        }

        return $progress . '/' . $this->progressTargetValue;
    }

    public function getRewardName(): ?string
    {
        return $this->rewardName;
    }

    public function getRewardImageUrl(): ?string
    {
        return $this->rewardImageUrl;
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getGameName(): string
    {
        return $this->gameName;
    }

    public function getGameStatus(): int
    {
        return $this->gameStatus;
    }

    public function getGameIcon(): string
    {
        return $this->gameIcon;
    }

    public function getGameIconRelativePath(): string
    {
        if ($this->gameIcon === '.png') {
            return $this->usesPs5Assets()
                ? '../missing-ps5-game-and-trophy.png'
                : '../missing-ps4-game.png';
        }

        return $this->gameIcon;
    }

    public function getPlatformString(): string
    {
        return $this->platforms;
    }

    /**
     * @return string[]
     */
    public function getPlatforms(): array
    {
        if ($this->platforms === '') {
            return [];
        }

        $platforms = array_map('trim', explode(',', $this->platforms));
        $platforms = array_filter(
            $platforms,
            static fn(string $value): bool => $value !== ''
        );

        return array_values($platforms);
    }

    public function requiresWarning(): bool
    {
        return $this->gameStatus !== 0 || $this->trophyStatus !== 0;
    }

    public function getEarnedDate(): string
    {
        return $this->earnedDate;
    }

    public function getEarnedBadgeElementId(): string
    {
        return 'trophy-earned-' . $this->trophyId;
    }

    public function getGameSlug(Utility $utility): string
    {
        return $this->gameId . '-' . $utility->slugify($this->gameName);
    }

    public function getTrophySlug(Utility $utility): string
    {
        return $this->trophyId . '-' . $utility->slugify($this->trophyName);
    }

    private static function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric((string) $value)) {
            return (int) $value;
        }

        return null;
    }

    private static function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }

    private function usesPs5Assets(): bool
    {
        $platforms = strtoupper($this->platforms);

        return str_contains($platforms, 'PS5') || str_contains($platforms, 'PSVR2');
    }
}
