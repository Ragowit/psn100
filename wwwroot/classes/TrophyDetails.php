<?php

declare(strict_types=1);

require_once __DIR__ . '/Utility.php';

final class TrophyDetails
{
    private const PLATFORM_SEPARATOR = ',';
    private const MISSING_PS5_ICON = '../missing-ps5-game-and-trophy.png';
    private const MISSING_PS4_GAME_ICON = '../missing-ps4-game.png';
    private const MISSING_PS4_TROPHY_ICON = '../missing-ps4-trophy.png';

    private int $id;

    private string $npCommunicationId;

    private int $groupId;

    private int $orderId;

    private string $type;

    private string $name;

    private string $detail;

    private string $iconFileName;

    private float $rarityPercent;

    private int $status;

    private ?string $progressTargetValue;

    private ?string $rewardName;

    private ?string $rewardImageUrl;

    private int $gameId;

    private string $gameName;

    private string $gameIconFileName;

    private string $platform;

    public function __construct(
        int $id,
        string $npCommunicationId,
        int $groupId,
        int $orderId,
        string $type,
        string $name,
        string $detail,
        string $iconFileName,
        float $rarityPercent,
        int $status,
        ?string $progressTargetValue,
        ?string $rewardName,
        ?string $rewardImageUrl,
        int $gameId,
        string $gameName,
        string $gameIconFileName,
        string $platform
    ) {
        $this->id = $id;
        $this->npCommunicationId = $npCommunicationId;
        $this->groupId = $groupId;
        $this->orderId = $orderId;
        $this->type = $type;
        $this->name = $name;
        $this->detail = $detail;
        $this->iconFileName = $iconFileName;
        $this->rarityPercent = $rarityPercent;
        $this->status = $status;
        $this->progressTargetValue = $progressTargetValue;
        $this->rewardName = $rewardName;
        $this->rewardImageUrl = $rewardImageUrl;
        $this->gameId = $gameId;
        $this->gameName = $gameName;
        $this->gameIconFileName = $gameIconFileName;
        $this->platform = $platform;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['trophy_id'] ?? 0),
            (string) ($data['np_communication_id'] ?? ''),
            (int) ($data['group_id'] ?? 0),
            (int) ($data['order_id'] ?? 0),
            (string) ($data['trophy_type'] ?? ''),
            (string) ($data['trophy_name'] ?? ''),
            (string) ($data['trophy_detail'] ?? ''),
            (string) ($data['trophy_icon'] ?? ''),
            isset($data['rarity_percent']) ? (float) $data['rarity_percent'] : 0.0,
            (int) ($data['status'] ?? 0),
            isset($data['progress_target_value']) ? (string) $data['progress_target_value'] : null,
            isset($data['reward_name']) ? (string) $data['reward_name'] : null,
            isset($data['reward_image_url']) ? (string) $data['reward_image_url'] : null,
            (int) ($data['game_id'] ?? 0),
            (string) ($data['game_name'] ?? ''),
            (string) ($data['game_icon'] ?? ''),
            (string) ($data['platform'] ?? '')
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getGroupId(): int
    {
        return $this->groupId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getIconFileName(): string
    {
        return $this->iconFileName;
    }

    public function getRarityPercent(): float
    {
        return $this->rarityPercent;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getProgressTargetValue(): ?string
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

    public function getGameIconFileName(): string
    {
        return $this->gameIconFileName;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * @return list<string>
     */
    public function getPlatforms(): array
    {
        $platforms = array_map('trim', explode(self::PLATFORM_SEPARATOR, $this->platform));

        return array_values(array_filter($platforms, static fn (string $platform): bool => $platform !== ''));
    }

    public function getGameIconPath(): string
    {
        if ($this->gameIconFileName === '.png') {
            return $this->usesPlayStation5Assets()
                ? self::MISSING_PS5_ICON
                : self::MISSING_PS4_GAME_ICON;
        }

        return $this->gameIconFileName;
    }

    public function getTrophyIconPath(): string
    {
        if ($this->iconFileName === '.png') {
            return $this->usesPlayStation5Assets()
                ? self::MISSING_PS5_ICON
                : self::MISSING_PS4_TROPHY_ICON;
        }

        return $this->iconFileName;
    }

    public function isUnobtainable(): bool
    {
        return $this->status === 1;
    }

    public function getGameSlug(Utility $utility): string
    {
        return $this->gameId . '-' . $utility->slugify($this->gameName);
    }

    public function getTrophySlug(Utility $utility): string
    {
        return $this->id . '-' . $utility->slugify($this->name);
    }

    public function getGameLink(Utility $utility, ?string $playerOnlineId = null): string
    {
        $slug = $this->getGameSlug($utility);

        if ($playerOnlineId === null || $playerOnlineId === '') {
            return '/game/' . $slug;
        }

        return '/game/' . $slug . '/' . rawurlencode($playerOnlineId);
    }

    private function usesPlayStation5Assets(): bool
    {
        return str_contains($this->platform, 'PS5');
    }
}
