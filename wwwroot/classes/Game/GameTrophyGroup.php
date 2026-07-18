<?php

declare(strict_types=1);

final readonly class GameTrophyGroup
{
    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public static function fromArray(array $data, bool $usesPlayStation5Assets): self
    {
        return new self(
            (string) ($data['group_id'] ?? ''),
            (string) ($data['name'] ?? ''),
            (string) ($data['detail'] ?? ''),
            (string) ($data['icon_url'] ?? ''),
            (int) ($data['bronze'] ?? 0),
            (int) ($data['silver'] ?? 0),
            (int) ($data['gold'] ?? 0),
            (int) ($data['platinum'] ?? 0),
            $usesPlayStation5Assets,
        );
    }

    private function __construct(
        private string $id,
        private string $name,
        private string $detail,
        private string $iconUrl,
        private int $bronzeCount,
        private int $silverCount,
        private int $goldCount,
        private int $platinumCount,
        private bool $usesPlayStation5Assets,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
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
                : '../missing-ps4-game.png';
        }

        return $this->iconUrl;
    }

    public function isDefaultGroup(): bool
    {
        return $this->id === 'default';
    }

    public function getBronzeCount(): int
    {
        return $this->bronzeCount;
    }

    public function getSilverCount(): int
    {
        return $this->silverCount;
    }

    public function getGoldCount(): int
    {
        return $this->goldCount;
    }

    public function getPlatinumCount(): int
    {
        return $this->platinumCount;
    }
}
