<?php

declare(strict_types=1);

class PlayerGame
{
    private const int STATUS_DELISTED = 1;
    private const int STATUS_OBSOLETE = 3;
    private const int STATUS_DELISTED_AND_OBSOLETE = 4;

    private int $id;
    private string $npCommunicationId;
    private string $name;
    private string $iconUrl;
    private string $platform;
    private int $status;
    private int $maxRarityPoints;
    private int $maxInGameRarityPoints;
    private int $bronze;
    private int $silver;
    private int $gold;
    private int $platinum;
    private int $progress;
    private string $lastUpdatedDate;
    private int $rarityPoints;
    private int $inGameRarityPoints;
    private ?string $completionDurationLabel;

    private function __construct()
    {
        $this->id = 0;
        $this->npCommunicationId = '';
        $this->name = '';
        $this->iconUrl = '';
        $this->platform = '';
        $this->status = 0;
        $this->maxRarityPoints = 0;
        $this->maxInGameRarityPoints = 0;
        $this->bronze = 0;
        $this->silver = 0;
        $this->gold = 0;
        $this->platinum = 0;
        $this->progress = 0;
        $this->lastUpdatedDate = '';
        $this->rarityPoints = 0;
        $this->inGameRarityPoints = 0;
        $this->completionDurationLabel = null;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row, ?string $completionDurationLabel = null): self
    {
        $game = new self();
        $game->id = (int) ($row['id'] ?? 0);
        $game->npCommunicationId = (string) ($row['np_communication_id'] ?? '');
        $game->name = (string) ($row['name'] ?? '');
        $game->iconUrl = (string) ($row['icon_url'] ?? '');
        $game->platform = (string) ($row['platform'] ?? '');
        $game->status = (int) ($row['status'] ?? 0);
        $game->maxRarityPoints = (int) ($row['max_rarity_points'] ?? 0);
        $game->maxInGameRarityPoints = (int) ($row['max_in_game_rarity_points'] ?? 0);
        $game->bronze = (int) ($row['bronze'] ?? 0);
        $game->silver = (int) ($row['silver'] ?? 0);
        $game->gold = (int) ($row['gold'] ?? 0);
        $game->platinum = (int) ($row['platinum'] ?? 0);
        $game->progress = (int) ($row['progress'] ?? 0);
        $game->lastUpdatedDate = (string) ($row['last_updated_date'] ?? '');
        $game->rarityPoints = (int) ($row['rarity_points'] ?? 0);
        $game->inGameRarityPoints = (int) ($row['in_game_rarity_points'] ?? 0);
        $game->completionDurationLabel = $completionDurationLabel;

        return $game;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIconFileName(): string
    {
        if ($this->iconUrl === '.png') {
            return str_contains($this->platform, 'PS5')
                ? '../missing-ps5-game-and-trophy.png'
                : '../missing-ps4-game.png';
        }

        return $this->iconUrl;
    }

    /**
     * @return array<int, string>
     */
    public function getPlatforms(): array
    {
        $platforms = array_map('trim', explode(',', $this->platform));

        return array_filter(
            $platforms,
            static fn(string $platform): bool => $platform !== ''
        );
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 0;
    }

    public function isCompleted(): bool
    {
        return $this->progress === 100;
    }

    public function getRowClass(): ?string
    {
        if ($this->status === self::STATUS_DELISTED || $this->status === self::STATUS_OBSOLETE || $this->status === self::STATUS_DELISTED_AND_OBSOLETE) {
            return 'table-warning';
        }

        if ($this->isCompleted()) {
            return 'table-success';
        }

        return null;
    }

    public function getRowTitle(): ?string
    {
        return match ($this->status) {
            self::STATUS_DELISTED => 'This game is delisted, no trophies will be accounted for on any leaderboard.',
            self::STATUS_OBSOLETE => 'This game is obsolete, no trophies will be accounted for on any leaderboard.',
            self::STATUS_DELISTED_AND_OBSOLETE => 'This game is delisted &amp; obsolete, no trophies will be accounted for on any leaderboard.',
            default => null,
        };
    }

    public function getBronze(): int
    {
        return $this->bronze;
    }

    public function getSilver(): int
    {
        return $this->silver;
    }

    public function getGold(): int
    {
        return $this->gold;
    }

    public function getPlatinum(): int
    {
        return $this->platinum;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getLastUpdatedDate(): string
    {
        return $this->lastUpdatedDate;
    }

    public function getRarityPoints(): int
    {
        return $this->rarityPoints;
    }

    public function getInGameRarityPoints(): int
    {
        return $this->inGameRarityPoints;
    }

    public function getMaxRarityPoints(): int
    {
        return $this->maxRarityPoints;
    }

    public function getMaxInGameRarityPoints(): int
    {
        return $this->maxInGameRarityPoints;
    }

    public function getCompletionDurationLabel(): ?string
    {
        return $this->completionDurationLabel;
    }
}
