<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';
require_once __DIR__ . '/GameAvailabilityStatus.php';
require_once __DIR__ . '/GameStatusBadge.php';

final readonly class PlayerGame
{
    private function __construct(
        private int $id,
        private string $npCommunicationId,
        private string $name,
        private string $iconUrl,
        private string $platform,
        private GameAvailabilityStatus $status,
        private int $maxRarityPoints,
        private int $maxInGameRarityPoints,
        private int $bronze,
        private int $silver,
        private int $gold,
        private int $platinum,
        private int $progress,
        private string $lastUpdatedDate,
        private int $rarityPoints,
        private int $inGameRarityPoints,
        private ?string $completionDurationLabel,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\NoDiscard]
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
        return CommaSeparatedValues::parseTrimmed($this->platform);
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
