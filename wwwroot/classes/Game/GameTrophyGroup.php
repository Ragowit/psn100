<?php

declare(strict_types=1);

final class GameTrophyGroup
{
    private string $id;

    private string $name;

    private string $detail;

    private string $iconUrl;

    private int $bronzeCount;

    private int $silverCount;

    private int $goldCount;

    private int $platinumCount;

    private bool $usesPlayStation5Assets;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, bool $usesPlayStation5Assets): self
    {
        return new self($data, $usesPlayStation5Assets);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data, bool $usesPlayStation5Assets)
    {
        $this->id = (string) ($data['group_id'] ?? '');
        $this->name = (string) ($data['name'] ?? '');
        $this->detail = (string) ($data['detail'] ?? '');
        $this->iconUrl = (string) ($data['icon_url'] ?? '');
        $this->bronzeCount = isset($data['bronze']) ? (int) $data['bronze'] : 0;
        $this->silverCount = isset($data['silver']) ? (int) $data['silver'] : 0;
        $this->goldCount = isset($data['gold']) ? (int) $data['gold'] : 0;
        $this->platinumCount = isset($data['platinum']) ? (int) $data['platinum'] : 0;
        $this->usesPlayStation5Assets = $usesPlayStation5Assets;
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
