<?php

declare(strict_types=1);

require_once __DIR__ . '/GameAvailabilityStatus.php';
require_once __DIR__ . '/GameStatusBadge.php';
require_once __DIR__ . '/Utility.php';

final class GameListItem
{
    private const int COMPLETION_PERCENTAGE = 100;
    private const string MISSING_PS5_ICON = '../missing-ps5-game-and-trophy.png';
    private const string MISSING_PS4_ICON = '../missing-ps4-game.png';

    private function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly GameAvailabilityStatus $status,
        private readonly string $iconUrl,
        private readonly string $platformValue,
        private readonly int $owners,
        private readonly int $rarityPoints,
        private readonly int $inGameRarityPoints,
        private readonly string $difficulty,
        private readonly int $platinum,
        private readonly int $gold,
        private readonly int $silver,
        private readonly int $bronze,
        private readonly int $progress,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : 0,
            (string) ($row['name'] ?? ''),
            GameAvailabilityStatus::fromInt((int) ($row['status'] ?? GameAvailabilityStatus::NORMAL->value)),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            isset($row['owners']) ? (int) $row['owners'] : 0,
            isset($row['rarity_points']) ? (int) $row['rarity_points'] : 0,
            isset($row['in_game_rarity_points']) ? (int) $row['in_game_rarity_points'] : 0,
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

        if ($this->status->isUnavailable()) {
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

    public function getInGameRarityPoints(): int
    {
        return $this->inGameRarityPoints;
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
        return $this->status === GameAvailabilityStatus::NORMAL;
    }

    public function getStatusBadge(): ?GameStatusBadge
    {
        $label = $this->status->badgeLabel();
        $message = $this->status->warningMessage();

        if ($label === null || $message === null) {
            return null;
        }

        return new GameStatusBadge($label, $message);
    }

    private function isPlayStation5Title(): bool
    {
        return str_contains($this->platformValue, 'PS5') || str_contains($this->platformValue, 'PSVR2');
    }
}
