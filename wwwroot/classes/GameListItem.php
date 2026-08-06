<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';
require_once __DIR__ . '/GameAvailabilityStatus.php';
require_once __DIR__ . '/GameStatusBadge.php';
require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/Utility.php';

final readonly class GameListItem
{
    private const int COMPLETION_PERCENTAGE = 100;
    private const string MISSING_PS5_ICON = '../missing-ps5-game-and-trophy.png';
    private const string MISSING_PS4_ICON = '../missing-ps4-game.png';

    private function __construct(
        final private int $id,
        final private string $name,
        final private GameAvailabilityStatus $status,
        final private string $iconUrl,
        final private string $platformValue,
        final private int $owners,
        final private int $rarityPoints,
        final private int $inGameRarityPoints,
        final private string $difficulty,
        final private int $platinum,
        final private int $gold,
        final private int $silver,
        final private int $bronze,
        final private int $progress,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            status: GameAvailabilityStatus::fromInt((int) ($row['status'] ?? GameAvailabilityStatus::NORMAL->value)),
            iconUrl: (string) ($row['icon_url'] ?? ''),
            platformValue: (string) ($row['platform'] ?? ''),
            owners: (int) ($row['owners'] ?? 0),
            rarityPoints: (int) ($row['rarity_points'] ?? 0),
            inGameRarityPoints: (int) ($row['in_game_rarity_points'] ?? 0),
            difficulty: (string) ($row['difficulty'] ?? '0'),
            platinum: (int) ($row['platinum'] ?? 0),
            gold: (int) ($row['gold'] ?? 0),
            silver: (int) ($row['silver'] ?? 0),
            bronze: (int) ($row['bronze'] ?? 0),
            progress: (int) ($row['progress'] ?? 0),
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
        return CommaSeparatedValues::parseTrimmed($this->platformValue);
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
        return Platform::usesPlayStation5Assets($this->platformValue);
    }
}
