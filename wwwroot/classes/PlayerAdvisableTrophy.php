<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';
require_once __DIR__ . '/TrophyType.php';

final readonly class PlayerAdvisableTrophy
{
    /**
     * @param string[] $platforms
     */
    private function __construct(
        final private int $trophyId,
        final private TrophyType $trophyType,
        final private string $trophyName,
        final private string $trophyDetail,
        final private string $trophyIcon,
        final private float $rarityPercent,
        final private float $inGameRarityPercent,
        final private ?int $progressTargetValue,
        final private ?string $rewardName,
        final private ?string $rewardImageUrl,
        final private int $gameId,
        final private string $gameName,
        final private string $gameIcon,
        final private array $platforms,
        final private ?float $progress,
        final private Utility $utility,
    ) {
    }

    #[\NoDiscard]
    public static function fromArray(array $data, Utility $utility): self
    {
        return new self(
            (int) ($data['trophy_id'] ?? 0),
            TrophyType::fromMixed($data['trophy_type'] ?? null),
            (string) ($data['trophy_name'] ?? ''),
            (string) ($data['trophy_detail'] ?? ''),
            (string) ($data['trophy_icon'] ?? ''),
            (float) ($data['rarity_percent'] ?? 0.0),
            (float) ($data['in_game_rarity_percent'] ?? 0.0),
            isset($data['progress_target_value']) ? (int) $data['progress_target_value'] : null,
            isset($data['reward_name']) ? (string) $data['reward_name'] : null,
            isset($data['reward_image_url']) ? (string) $data['reward_image_url'] : null,
            (int) ($data['game_id'] ?? 0),
            (string) ($data['game_name'] ?? ''),
            (string) ($data['game_icon'] ?? ''),
            CommaSeparatedValues::parseTrimmed((string) ($data['platform'] ?? '')),
            isset($data['progress']) ? (float) $data['progress'] : null,
            $utility
        );
    }

    public function getTrophyId(): int
    {
        return $this->trophyId;
    }

    public function getTrophyType(): TrophyType
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

    public function getInGameRarityPercent(): float
    {
        return $this->inGameRarityPercent;
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
        $slug = $this->gameId . '-' . $this->utility->slugify($this->gameName);

        if ($playerOnlineId === '') {
            return $slug;
        }

        return $slug . '/' . rawurlencode($playerOnlineId);
    }

    public function getTrophyLink(string $playerOnlineId): string
    {
        $slug = $this->trophyId . '-' . $this->utility->slugify($this->trophyName);

        if ($playerOnlineId === '') {
            return $slug;
        }

        return $slug . '/' . rawurlencode($playerOnlineId);
    }

    /**
     * @return string[]
     */
    public function getPlatforms(): array
    {
        return $this->platforms;
    }

    private function usesPlayStation5Assets(): bool
    {
        return array_any(
            $this->platforms,
            static fn (string $platform): bool => str_contains($platform, 'PS5') || str_contains($platform, 'PSVR2')
        );
    }
}
