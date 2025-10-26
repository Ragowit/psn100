<?php

declare(strict_types=1);

class PlayerRandomGame
{
    private int $id;

    private string $npCommunicationId;

    private string $name;

    private string $iconUrl;

    private string $platform;

    private int $owners;

    private string $difficulty;

    private int $platinum;

    private int $gold;

    private int $silver;

    private int $bronze;

    private int $rarityPoints;

    private ?string $progress;

    private Utility $utility;

    public function __construct(array $data, Utility $utility)
    {
        $this->id = isset($data['id']) ? (int) $data['id'] : 0;
        $this->npCommunicationId = (string) ($data['np_communication_id'] ?? '');
        $this->name = (string) ($data['name'] ?? '');
        $this->iconUrl = (string) ($data['icon_url'] ?? '');
        $this->platform = (string) ($data['platform'] ?? '');
        $this->owners = isset($data['owners']) ? (int) $data['owners'] : 0;
        $this->difficulty = (string) ($data['difficulty'] ?? '0');
        $this->platinum = isset($data['platinum']) ? (int) $data['platinum'] : 0;
        $this->gold = isset($data['gold']) ? (int) $data['gold'] : 0;
        $this->silver = isset($data['silver']) ? (int) $data['silver'] : 0;
        $this->bronze = isset($data['bronze']) ? (int) $data['bronze'] : 0;
        $this->rarityPoints = isset($data['rarity_points']) ? (int) $data['rarity_points'] : 0;
        $this->progress = array_key_exists('progress', $data) ? (string) $data['progress'] : null;
        $this->utility = $utility;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwners(): int
    {
        return $this->owners;
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

    public function getProgress(): ?string
    {
        return $this->progress;
    }

    public function getPlatforms(): array
    {
        if ($this->platform === '') {
            return [];
        }

        $platforms = array_map('trim', explode(',', $this->platform));
        $platforms = array_filter(
            $platforms,
            static fn(string $value): bool => $value !== ''
        );

        return array_values($platforms);
    }

    public function getIconUrl(): string
    {
        if ($this->iconUrl === '.png') {
            if (str_contains($this->platform, 'PS5') || str_contains($this->platform, 'PSVR2')) {
                return '../missing-ps5-game-and-trophy.png';
            }

            return '../missing-ps4-game.png';
        }

        return $this->iconUrl;
    }

    public function getGameLink(string $playerOnlineId): string
    {
        $slug = $this->id . '-' . $this->utility->slugify($this->name);

        if ($playerOnlineId === '') {
            return $slug;
        }

        return $slug . '/' . rawurlencode($playerOnlineId);
    }
}
