<?php

declare(strict_types=1);

require_once __DIR__ . '/Utility.php';

class TrophyListItem
{
    private const PLATFORM_SEPARATOR = ',';
    private const MISSING_PS5_ICON = '../missing-ps5-game-and-trophy.png';
    private const MISSING_PS4_GAME_ICON = '../missing-ps4-game.png';
    private const MISSING_PS4_TROPHY_ICON = '../missing-ps4-trophy.png';

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

    private string $platform;

    public function __construct(
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
        string $platform
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
        $this->platform = $platform;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['trophy_id'] ?? 0),
            (string) ($data['trophy_type'] ?? ''),
            (string) ($data['trophy_name'] ?? ''),
            (string) ($data['trophy_detail'] ?? ''),
            (string) ($data['trophy_icon'] ?? ''),
            (float) ($data['rarity_percent'] ?? 0.0),
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
        $platforms = array_map('trim', explode(self::PLATFORM_SEPARATOR, $this->platform));

        return array_values(array_filter($platforms, static fn (string $platform): bool => $platform !== ''));
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
