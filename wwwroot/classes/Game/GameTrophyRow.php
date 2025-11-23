<?php

declare(strict_types=1);

require_once __DIR__ . '/../Utility.php';

final class GameTrophyRow
{
    private const TROPHY_TYPE_COLORS = [
        'bronze' => '#c46438',
        'silver' => '#777777',
        'gold' => '#c2903e',
        'platinum' => '#667fb2',
    ];

    private const TROPHY_TYPE_ICONS = [
        'bronze' => '/img/trophy-bronze.svg',
        'silver' => '/img/trophy-silver.svg',
        'gold' => '/img/trophy-gold.svg',
        'platinum' => '/img/trophy-platinum.svg',
    ];

    private const UNOBTAINABLE_STATUS = 1;
    private const UNOBTAINABLE_TITLE = 'This trophy is unobtainable and not accounted for on any leaderboard.';

    private int $id;
    private int $orderId;
    private string $type;
    private string $name;
    private string $detail;
    private string $iconUrl;
    private float $rarityPercent;
    private ?float $inGameRarityPercent;
    private int $status;
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
        $this->type = (string) ($data['type'] ?? '');
        $this->name = (string) ($data['name'] ?? '');
        $this->detail = (string) ($data['detail'] ?? '');
        $this->iconUrl = (string) ($data['icon_url'] ?? '');
        $this->rarityPercent = isset($data['rarity_percent']) ? (float) $data['rarity_percent'] : 0.0;
        $this->inGameRarityPercent = isset($data['in_game_rarity_percent'])
            ? (float) $data['in_game_rarity_percent']
            : 0.0;
        $this->status = isset($data['status']) ? (int) $data['status'] : 0;
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

        $earnedValue = isset($data['earned']) ? (int) $data['earned'] : 0;
        $this->isEarned = $earnedValue === 1;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeIconPath(): string
    {
        return self::TROPHY_TYPE_ICONS[$this->type] ?? self::TROPHY_TYPE_ICONS['bronze'];
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

        $progress = $this->progress;

        if ($progress === null && $this->isEarned) {
            $progress = $this->progressTargetValue;
        }

        $progressValue = $progress ?? 0;

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

    public function getStatus(): int
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
        return self::TROPHY_TYPE_COLORS[$this->type] ?? self::TROPHY_TYPE_COLORS['bronze'];
    }

    public function getRowAttributes(?int $accountId): string
    {
        $attributes = [];

        if ($this->isUnobtainable()) {
            $attributes[] = 'class="table-warning"';
            $attributes[] = 'title="' . htmlspecialchars(self::UNOBTAINABLE_TITLE, ENT_QUOTES, 'UTF-8') . '"';
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
        return $this->status === self::UNOBTAINABLE_STATUS;
    }
}
