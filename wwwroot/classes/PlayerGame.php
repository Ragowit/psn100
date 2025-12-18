<?php

declare(strict_types=1);

require_once __DIR__ . '/GameAvailabilityStatus.php';

class PlayerGame
{
    private function __construct(
        private readonly int $id,
        private readonly string $npCommunicationId,
        private readonly string $name,
        private readonly string $iconUrl,
        private readonly string $platform,
        private readonly GameAvailabilityStatus $status,
        private readonly int $maxRarityPoints,
        private readonly int $maxInGameRarityPoints,
        private readonly int $bronze,
        private readonly int $silver,
        private readonly int $gold,
        private readonly int $platinum,
        private readonly int $progress,
        private readonly string $lastUpdatedDate,
        private readonly int $rarityPoints,
        private readonly int $inGameRarityPoints,
        private readonly ?string $completionDurationLabel,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row, ?string $completionDurationLabel = null): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['np_communication_id'] ?? ''),
            (string) ($row['name'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['platform'] ?? ''),
            GameAvailabilityStatus::fromInt((int) ($row['status'] ?? 0)),
            (int) ($row['max_rarity_points'] ?? 0),
            (int) ($row['max_in_game_rarity_points'] ?? 0),
            (int) ($row['bronze'] ?? 0),
            (int) ($row['silver'] ?? 0),
            (int) ($row['gold'] ?? 0),
            (int) ($row['platinum'] ?? 0),
            (int) ($row['progress'] ?? 0),
            (string) ($row['last_updated_date'] ?? ''),
            (int) ($row['rarity_points'] ?? 0),
            (int) ($row['in_game_rarity_points'] ?? 0),
            $completionDurationLabel,
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
        return $this->status->value;
    }

    public function getAvailabilityStatus(): GameAvailabilityStatus
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === GameAvailabilityStatus::NORMAL;
    }

    public function isCompleted(): bool
    {
        return $this->progress === 100;
    }

    public function getRowClass(): ?string
    {
        if ($this->status->isUnavailable()) {
            return 'table-warning';
        }

        if ($this->isCompleted()) {
            return 'table-success';
        }

        return null;
    }

    public function getRowTitle(): ?string
    {
        return $this->status->warningMessage();
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
