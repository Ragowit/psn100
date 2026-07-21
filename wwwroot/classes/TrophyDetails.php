<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';
require_once __DIR__ . '/PlayerUrlBuilder.php';
require_once __DIR__ . '/TrophyType.php';
require_once __DIR__ . '/TrophyMetaStatus.php';
require_once __DIR__ . '/Utility.php';

final readonly class TrophyDetails
{
    private const string MISSING_PS5_ICON = '../missing-ps5-game-and-trophy.png';
    private const string MISSING_PS4_GAME_ICON = '../missing-ps4-game.png';
    private const string MISSING_PS4_TROPHY_ICON = '../missing-ps4-trophy.png';

    public function __construct(
        final private int $id,
        final private string $npCommunicationId,
        final private int $groupId,
        final private int $orderId,
        final private TrophyType $type,
        final private string $name,
        final private string $detail,
        final private string $iconFileName,
        final private float $rarityPercent,
        final private ?float $inGameRarityPercent,
        final private TrophyMetaStatus $status,
        final private ?string $progressTargetValue,
        final private ?string $rewardName,
        final private ?string $rewardImageUrl,
        final private int $gameId,
        final private string $gameName,
        final private string $gameIconFileName,
        final private string $platform
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['trophy_id'] ?? 0),
            (string) ($data['np_communication_id'] ?? ''),
            (int) ($data['group_id'] ?? 0),
            (int) ($data['order_id'] ?? 0),
            TrophyType::fromMixed($data['trophy_type'] ?? null),
            (string) ($data['trophy_name'] ?? ''),
            (string) ($data['trophy_detail'] ?? ''),
            (string) ($data['trophy_icon'] ?? ''),
            (float) ($data['rarity_percent'] ?? 0.0),
            (float) ($data['in_game_rarity_percent'] ?? 0.0),
            TrophyMetaStatus::fromMixed($data['status'] ?? 0),
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

    public function getType(): TrophyType
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

    public function getInGameRarityPercent(): ?float
    {
        return $this->inGameRarityPercent;
    }

    public function getStatus(): TrophyMetaStatus
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
        return CommaSeparatedValues::parseTrimmed($this->platform);
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
        return $this->status->isUnobtainable();
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
        return PlayerUrlBuilder::gamePath($this->getGameSlug($utility), $playerOnlineId);
    }

    private function usesPlayStation5Assets(): bool
    {
        return str_contains($this->platform, 'PS5');
    }
}
