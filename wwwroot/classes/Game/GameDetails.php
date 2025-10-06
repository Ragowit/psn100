<?php

declare(strict_types=1);

class GameDetails
{
    private int $id;

    private string $name;

    private string $npCommunicationId;

    private ?string $parentNpCommunicationId;

    private string $platform;

    private string $iconUrl;

    private string $setVersion;

    private ?string $region;

    private ?string $message;

    private int $platinum;

    private int $gold;

    private int $silver;

    private int $bronze;

    private int $ownersCompleted;

    private int $owners;

    private string $difficulty;

    private int $status;

    private int $rarityPoints;

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $game = new self();

        $game->id = (int) ($row['id'] ?? 0);
        $game->name = (string) ($row['name'] ?? '');
        $game->npCommunicationId = (string) ($row['np_communication_id'] ?? '');
        $game->parentNpCommunicationId = isset($row['parent_np_communication_id'])
            ? self::toNullableString($row['parent_np_communication_id'])
            : null;
        $game->platform = (string) ($row['platform'] ?? '');
        $game->iconUrl = (string) ($row['icon_url'] ?? '');
        $game->setVersion = (string) ($row['set_version'] ?? '');
        $game->region = self::toNullableString($row['region'] ?? null);
        $game->message = self::toNullableString($row['message'] ?? null);
        $game->platinum = (int) ($row['platinum'] ?? 0);
        $game->gold = (int) ($row['gold'] ?? 0);
        $game->silver = (int) ($row['silver'] ?? 0);
        $game->bronze = (int) ($row['bronze'] ?? 0);
        $game->ownersCompleted = (int) ($row['owners_completed'] ?? 0);
        $game->owners = (int) ($row['owners'] ?? 0);
        $game->difficulty = (string) ($row['difficulty'] ?? '0');
        $game->status = (int) ($row['status'] ?? 0);
        $game->rarityPoints = (int) ($row['rarity_points'] ?? 0);

        return $game;
    }

    private static function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;

        return $stringValue === '' ? null : $stringValue;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getParentNpCommunicationId(): ?string
    {
        return $this->parentNpCommunicationId;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function getIconUrl(): string
    {
        return $this->iconUrl;
    }

    public function getSetVersion(): string
    {
        return $this->setVersion;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function hasMessage(): bool
    {
        return $this->message !== null && $this->message !== '';
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

    public function getOwnersCompleted(): int
    {
        return $this->ownersCompleted;
    }

    public function getOwners(): int
    {
        return $this->owners;
    }

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getRarityPoints(): int
    {
        return $this->rarityPoints;
    }
}

