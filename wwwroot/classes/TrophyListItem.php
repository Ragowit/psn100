<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';
require_once __DIR__ . '/TrophyType.php';
require_once __DIR__ . '/Utility.php';

final readonly class TrophyListItem
{
    private const string MISSING_PS5_ICON = '../missing-ps5-game-and-trophy.png';
    private const string MISSING_PS4_GAME_ICON = '../missing-ps4-game.png';
    private const string MISSING_PS4_TROPHY_ICON = '../missing-ps4-trophy.png';

    public function __construct(
        final private int $trophyId,
        final private TrophyType $trophyType,
        final private string $trophyName,
        final private string $trophyDetail,
        final private string $trophyIcon,
        final private float $rarityPercent,
        final private ?float $inGameRarityPercent,
        final private ?int $progressTargetValue,
        final private ?string $rewardName,
        final private ?string $rewardImageUrl,
        final private int $gameId,
        final private string $gameName,
        final private string $gameIcon,
        final private string $platform,
    ) {
    }

    #[\NoDiscard]
    public static function fromArray(array $data): self
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
            (string) ($data['platform'] ?? '')
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

    public function getTrophyIcon(): string
    {
        return $this->trophyIcon;
    }

    public function getTrophyIconPath(): string
    {
        if ($this->trophyIcon === '.png') {
            return $this->usesPlayStation5Assets()
                ? self::MISSING_PS5_ICON
                : self::MISSING_PS4_TROPHY_ICON;
        }

        return $this->trophyIcon;
    }

    public function getRarityPercent(): float
    {
        return $this->rarityPercent;
    }

    public function getInGameRarityPercent(): ?float
    {
        return $this->inGameRarityPercent;
    }

    public function getProgressTargetValue(): ?int
    {
        return $this->progressTargetValue;
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

    public function getGameIcon(): string
    {
        return $this->gameIcon;
    }

    public function getGameIconPath(): string
    {
        if ($this->gameIcon === '.png') {
            return $this->usesPlayStation5Assets()
                ? self::MISSING_PS5_ICON
                : self::MISSING_PS4_GAME_ICON;
        }

        return $this->gameIcon;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * @return string[]
     */
    public function getPlatforms(): array
    {
        return CommaSeparatedValues::parseTrimmed($this->platform);
    }

    public function getGameUrl(Utility $utility): string
    {
        return '/game/' . $this->gameId . '-' . $utility->slugify($this->gameName);
    }

    public function getTrophyUrl(Utility $utility): string
    {
        return '/trophy/' . $this->trophyId . '-' . $utility->slugify($this->trophyName);
    }

    private function usesPlayStation5Assets(): bool
    {
        return str_contains($this->platform, 'PS5');
    }
}
