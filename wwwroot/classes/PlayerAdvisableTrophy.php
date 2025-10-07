<?php

declare(strict_types=1);

class PlayerAdvisableTrophy
{
    private int $trophyId;

    private string $trophyType;

    private string $trophyName;

    private string $trophyDetail;

    private string $trophyIcon;

    private float $rarityPercent;

    private ?int $progressTargetValue;

    private ?string $rewardName;

    private ?string $rewardImageUrl;

    private int $gameId;

    private string $gameName;

    private string $gameIcon;

    /**
     * @var string[]
     */
    private array $platforms;

    private ?float $progress;

    private Utility $utility;

    private function __construct(
        int $trophyId,
        string $trophyType,
        string $trophyName,
        string $trophyDetail,
        string $trophyIcon,
        float $rarityPercent,
        ?int $progressTargetValue,
        ?string $rewardName,
        ?string $rewardImageUrl,
        int $gameId,
        string $gameName,
        string $gameIcon,
        string $platformsRaw,
        ?float $progress,
        Utility $utility
    ) {
        $this->trophyId = $trophyId;
        $this->trophyType = $trophyType;
        $this->trophyName = $trophyName;
        $this->trophyDetail = $trophyDetail;
        $this->trophyIcon = $trophyIcon;
        $this->rarityPercent = $rarityPercent;
        $this->progressTargetValue = $progressTargetValue;
        $this->rewardName = $rewardName;
        $this->rewardImageUrl = $rewardImageUrl;
        $this->gameId = $gameId;
        $this->gameName = $gameName;
        $this->gameIcon = $gameIcon;
        $this->platforms = $this->parsePlatforms($platformsRaw);
        $this->progress = $progress;
        $this->utility = $utility;
    }

    public static function fromArray(array $data, Utility $utility): self
    {
        return new self(
            isset($data['trophy_id']) ? (int) $data['trophy_id'] : 0,
            (string) ($data['trophy_type'] ?? ''),
            (string) ($data['trophy_name'] ?? ''),
            (string) ($data['trophy_detail'] ?? ''),
            (string) ($data['trophy_icon'] ?? ''),
            isset($data['rarity_percent']) ? (float) $data['rarity_percent'] : 0.0,
            isset($data['progress_target_value']) ? (int) $data['progress_target_value'] : null,
            array_key_exists('reward_name', $data) ? ($data['reward_name'] !== null ? (string) $data['reward_name'] : null) : null,
            array_key_exists('reward_image_url', $data) ? ($data['reward_image_url'] !== null ? (string) $data['reward_image_url'] : null) : null,
            isset($data['game_id']) ? (int) $data['game_id'] : 0,
            (string) ($data['game_name'] ?? ''),
            (string) ($data['game_icon'] ?? ''),
            (string) ($data['platform'] ?? ''),
            array_key_exists('progress', $data) ? ($data['progress'] !== null ? (float) $data['progress'] : null) : null,
            $utility
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

    public function getTrophyIconUrl(): string
    {
        if ($this->trophyIcon === '.png') {
            if ($this->usesPlayStation5Assets()) {
                return '../missing-ps5-game-and-trophy.png';
            }

            return '../missing-ps4-trophy.png';
        }

        return $this->trophyIcon;
    }

    public function getRarityPercent(): float
    {
        return $this->rarityPercent;
    }

    public function hasProgressTarget(): bool
    {
        return $this->progressTargetValue !== null;
    }

    public function getProgressTargetLabel(): ?string
    {
        if (!$this->hasProgressTarget()) {
            return null;
        }

        $progress = $this->progress !== null ? (string) $this->progress : '0';

        return $progress . '/' . $this->progressTargetValue;
    }

    public function hasReward(): bool
    {
        return $this->rewardName !== null && $this->rewardImageUrl !== null;
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

    public function getGameIconUrl(): string
    {
        if ($this->gameIcon === '.png') {
            if ($this->usesPlayStation5Assets()) {
                return '../missing-ps5-game-and-trophy.png';
            }

            return '../missing-ps4-game.png';
        }

        return $this->gameIcon;
    }

    public function getGameLink(string $playerOnlineId): string
    {
        return $this->gameId . '-' . $this->utility->slugify($this->gameName) . '/' . $playerOnlineId;
    }

    public function getTrophyLink(string $playerOnlineId): string
    {
        return $this->trophyId . '-' . $this->utility->slugify($this->trophyName) . '/' . $playerOnlineId;
    }

    /**
     * @return string[]
     */
    public function getPlatforms(): array
    {
        return $this->platforms;
    }

    private function parsePlatforms(string $platformsRaw): array
    {
        if ($platformsRaw === '') {
            return [];
        }

        $platforms = array_map('trim', explode(',', $platformsRaw));
        $platforms = array_filter(
            $platforms,
            static fn(string $value): bool => $value !== ''
        );

        return array_values($platforms);
    }

    private function usesPlayStation5Assets(): bool
    {
        foreach ($this->platforms as $platform) {
            if (str_contains($platform, 'PS5') || str_contains($platform, 'PSVR2')) {
                return true;
            }
        }

        return false;
    }
}
