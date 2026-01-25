<?php

declare(strict_types=1);

final readonly class PlayerTimeline
{
    private function __construct(
        private int $id,
        private string $npCommunicationId,
        private string $name,
        private string $iconUrl,
        private string $platform,
        private int $owners,
        private string $difficulty,
        private int $platinum,
        private int $gold,
        private int $silver,
        private int $bronze,
        private int $rarityPoints,
        private int $inGameRarityPoints,
        private ?string $progress,
        private Utility $utility,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, Utility $utility): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            npCommunicationId: (string) ($data['np_communication_id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            iconUrl: (string) ($data['icon_url'] ?? ''),
            platform: (string) ($data['platform'] ?? ''),
            owners: (int) ($data['owners'] ?? 0),
            difficulty: (string) ($data['difficulty'] ?? '0'),
            platinum: (int) ($data['platinum'] ?? 0),
            gold: (int) ($data['gold'] ?? 0),
            silver: (int) ($data['silver'] ?? 0),
            bronze: (int) ($data['bronze'] ?? 0),
            rarityPoints: (int) ($data['rarity_points'] ?? 0),
            inGameRarityPoints: (int) ($data['in_game_rarity_points'] ?? 0),
            progress: array_key_exists('progress', $data) ? (string) $data['progress'] : null,
            utility: $utility,
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

    public function getInGameRarityPoints(): int
    {
        return $this->inGameRarityPoints;
    }

    public function getProgress(): ?string
    {
        return $this->progress;
    }

    /**
     * @return list<string>
     */
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
