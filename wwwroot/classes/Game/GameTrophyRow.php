<?php

declare(strict_types=1);

require_once __DIR__ . '/../Utility.php';
require_once __DIR__ . '/../TrophyType.php';
require_once __DIR__ . '/../TrophyMetaStatus.php';
require_once __DIR__ . '/../Html.php';

final readonly class GameTrophyRow
{
    private const string UNOBTAINABLE_TITLE = 'This trophy is unobtainable and not accounted for on any leaderboard.';

    private int $id;
    private int $orderId;
    private TrophyType $type;
    private string $name;
    private string $detail;
    private string $iconUrl;
    private float $rarityPercent;
    private ?float $inGameRarityPercent;
    private TrophyMetaStatus $status;
    private ?int $progressTargetValue;
    private ?int $progress;
    private ?string $rewardName;
    private ?string $rewardImageUrl;
    private ?string $earnedDate;
    private bool $hasRecordedEarnedTimestamp;
    private bool $hasExplicitNoTimestamp;
    private bool $isEarned;
    private bool $usesPlayStation5Assets;
    private Utility $utility;

    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public static function fromArray(array $data, Utility $utility, bool $usesPlayStation5Assets): self
    {
        return new self($data, $utility, $usesPlayStation5Assets);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data, Utility $utility, bool $usesPlayStation5Assets)
    {
        $this->id = (int) ($data['id'] ?? 0);
        $this->orderId = (int) ($data['order_id'] ?? 0);
        $this->type = TrophyType::fromMixed($data['type'] ?? null);
        $this->name = (string) ($data['name'] ?? '');
        $this->detail = (string) ($data['detail'] ?? '');
        $this->iconUrl = (string) ($data['icon_url'] ?? '');
        $this->rarityPercent = (float) ($data['rarity_percent'] ?? 0.0);
        $this->inGameRarityPercent = (float) ($data['in_game_rarity_percent'] ?? 0.0);
        $this->status = TrophyMetaStatus::fromMixed($data['status'] ?? 0);
        $this->progressTargetValue = isset($data['progress_target_value'])
            ? (int) $data['progress_target_value']
            : null;
        $this->progress = isset($data['progress']) ? (int) $data['progress'] : null;
        $this->rewardName = isset($data['reward_name']) ? (string) $data['reward_name'] : null;
        $this->rewardImageUrl = isset($data['reward_image_url']) ? (string) $data['reward_image_url'] : null;
        $this->utility = $utility;
        $this->usesPlayStation5Assets = $usesPlayStation5Assets;

        $rawEarnedDate = isset($data['earned_date']) ? (string) $data['earned_date'] : null;
        $this->hasExplicitNoTimestamp = $rawEarnedDate === 'No Timestamp';
        $this->hasRecordedEarnedTimestamp = $rawEarnedDate !== null
            && $rawEarnedDate !== ''
            && $rawEarnedDate !== 'No Timestamp';
        $this->earnedDate = $this->hasRecordedEarnedTimestamp ? $rawEarnedDate : null;

        $this->isEarned = (int) ($data['earned'] ?? 0) === 1;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getType(): TrophyType
    {
        return $this->type;
    }

    public function getTrophyType(): TrophyType
    {
        return $this->type;
    }

    public function getTypeIconPath(): string
    {
        return $this->type->iconPath();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getIconPath(): string
    {
        if ($this->iconUrl === '.png') {
            return $this->usesPlayStation5Assets
                ? '../missing-ps5-game-and-trophy.png'
                : '../missing-ps4-trophy.png';
        }

        return $this->iconUrl;
    }

    public function getProgressDisplay(): ?string
    {
        if ($this->progressTargetValue === null) {
            return null;
        }

        $progressValue = $this->isEarned
            ? $this->progressTargetValue
            : ($this->progress ?? 0);

        return $progressValue . '/' . $this->progressTargetValue;
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

    public function isEarned(): bool
    {
        return $this->isEarned;
    }

    public function hasRecordedEarnedDate(): bool
    {
        return $this->hasRecordedEarnedTimestamp;
    }

    public function shouldDisplayNoTimestampMessage(): bool
    {
        return $this->isEarned && !$this->hasRecordedEarnedTimestamp && $this->hasExplicitNoTimestamp;
    }

    public function getEarnedDate(): ?string
    {
        return $this->earnedDate;
    }

    public function getEarnedElementId(): string
    {
        return 'earned' . $this->orderId;
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

    public function getTrophyLink(?string $playerOnlineId = null): string
    {
        $slug = $this->utility->slugify($this->name);
        $playerSegment = ($playerOnlineId !== null && $playerOnlineId !== '')
            ? '/' . $playerOnlineId
            : '';

        return '/trophy/' . $this->id . '-' . $slug . $playerSegment;
    }

    public function getTypeColor(): string
    {
        return $this->type->color();
    }

    public function getRowAttributes(?int $accountId): string
    {
        $attributes = [];

        if ($this->isUnobtainable()) {
            $attributes[] = 'class="table-warning"';
            $attributes[] = 'title="' . Html::escape(self::UNOBTAINABLE_TITLE) . '"';
        } elseif ($accountId !== null && $this->isEarned) {
            $attributes[] = 'class="table-success"';
        }

        if ($attributes === []) {
            return '';
        }

        return ' ' . implode(' ', $attributes);
    }

    private function isUnobtainable(): bool
    {
        return $this->status->isUnobtainable();
    }
}
