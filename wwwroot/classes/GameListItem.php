<?php

declare(strict_types=1);

require_once __DIR__ . '/GameStatusBadge.php';
require_once __DIR__ . '/Utility.php';

final class GameListItem
{
    private const STATUS_NORMAL = 0;
    private const STATUS_DELISTED = 1;
    private const STATUS_OBSOLETE = 3;
    private const STATUS_DELISTED_AND_OBSOLETE = 4;
    private const COMPLETION_PERCENTAGE = 100;
    private const MISSING_PS5_ICON = '../missing-ps5-game-and-trophy.png';
    private const MISSING_PS4_ICON = '../missing-ps4-game.png';

    private int $id;

    private string $name;

    private int $status;

    private string $iconUrl;

    private string $platformValue;

    private int $owners;

    private int $rarityPoints;

    private string $difficulty;

    private int $platinum;

    private int $gold;

    private int $silver;

    private int $bronze;

    private int $progress;

    private function __construct(
        int $id,
        string $name,
        int $status,
        string $iconUrl,
        string $platformValue,
        int $owners,
        int $rarityPoints,
        string $difficulty,
        int $platinum,
        int $gold,
        int $silver,
        int $bronze,
        int $progress
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->iconUrl = $iconUrl;
        $this->platformValue = $platformValue;
        $this->owners = $owners;
        $this->rarityPoints = $rarityPoints;
        $this->difficulty = $difficulty;
        $this->platinum = $platinum;
        $this->gold = $gold;
        $this->silver = $silver;
        $this->bronze = $bronze;
        $this->progress = $progress;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : 0,
            (string) ($row['name'] ?? ''),
            isset($row['status']) ? (int) $row['status'] : self::STATUS_NORMAL,
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            isset($row['owners']) ? (int) $row['owners'] : 0,
            isset($row['rarity_points']) ? (int) $row['rarity_points'] : 0,
            (string) ($row['difficulty'] ?? '0'),
            isset($row['platinum']) ? (int) $row['platinum'] : 0,
            isset($row['gold']) ? (int) $row['gold'] : 0,
            isset($row['silver']) ? (int) $row['silver'] : 0,
            isset($row['bronze']) ? (int) $row['bronze'] : 0,
            isset($row['progress']) ? (int) $row['progress'] : 0
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCardBackgroundClass(): string
    {
        if ($this->isCompleted()) {
            return 'bg-success-subtle';
        }

        if ($this->status === self::STATUS_DELISTED || $this->status === self::STATUS_OBSOLETE || $this->status === self::STATUS_DELISTED_AND_OBSOLETE) {
            return 'bg-warning-subtle';
        }

        return 'bg-body-tertiary';
    }

    public function getRelativeUrl(Utility $utility, ?string $playerName): string
    {
        $slug = $utility->slugify($this->name);
        $url = $this->id . '-' . $slug;

        if ($playerName !== null && $playerName !== '') {
            $url .= '/' . $playerName;
        }

        return $url;
    }

    public function getIconPath(): string
    {
        if ($this->iconUrl === '.png' || $this->iconUrl === '') {
            return $this->isPlayStation5Title() ? self::MISSING_PS5_ICON : self::MISSING_PS4_ICON;
        }

        return $this->iconUrl;
    }

    /**
     * @return string[]
     */
    public function getPlatforms(): array
    {
        if ($this->platformValue === '') {
            return [];
        }

        $platforms = array_map('trim', explode(',', $this->platformValue));
        $platforms = array_filter($platforms, static fn(string $platform): bool => $platform !== '');

        return array_values($platforms);
    }

    public function getOwners(): int
    {
        return $this->owners;
    }

    public function getOwnersLabel(): string
    {
        return $this->owners === 1 ? 'owner' : 'owners';
    }

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function getPlatinum(): int
    {
        return $this->platinum;
    }

    public function getGold(): int
    {
        return $this->gold;
    }

    public function getSilver(): int
    {
        return $this->silver;
    }

    public function getBronze(): int
    {
        return $this->bronze;
    }

    public function getRarityPoints(): int
    {
        return $this->rarityPoints;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function isCompleted(): bool
    {
        return $this->progress >= self::COMPLETION_PERCENTAGE;
    }

    public function shouldShowRarityPoints(): bool
    {
        return $this->status === self::STATUS_NORMAL;
    }

    public function getStatusBadge(): ?GameStatusBadge
    {
        return match ($this->status) {
            self::STATUS_DELISTED => new GameStatusBadge(
                'Delisted',
                "This game is delisted, no trophies will be accounted for on any leaderboard."
            ),
            self::STATUS_OBSOLETE => new GameStatusBadge(
                'Obsolete',
                "This game is obsolete, no trophies will be accounted for on any leaderboard."
            ),
            self::STATUS_DELISTED_AND_OBSOLETE => new GameStatusBadge(
                'Delisted & Obsolete',
                "This game is delisted & obsolete, no trophies will be accounted for on any leaderboard."
            ),
            default => null,
        };
    }

    private function isPlayStation5Title(): bool
    {
        return str_contains($this->platformValue, 'PS5') || str_contains($this->platformValue, 'PSVR2');
    }
}
