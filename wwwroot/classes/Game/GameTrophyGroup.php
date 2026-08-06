<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrophyGroupId.php';

final readonly class GameTrophyGroup
{
    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public static function fromArray(array $data, bool $usesPlayStation5Assets): self
    {
        return new self(
            id: (string) ($data['group_id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            detail: (string) ($data['detail'] ?? ''),
            iconUrl: (string) ($data['icon_url'] ?? ''),
            bronzeCount: (int) ($data['bronze'] ?? 0),
            silverCount: (int) ($data['silver'] ?? 0),
            goldCount: (int) ($data['gold'] ?? 0),
            platinumCount: (int) ($data['platinum'] ?? 0),
            usesPlayStation5Assets: $usesPlayStation5Assets,
        );
    }

    private function __construct(
        final private string $id,
        final private string $name,
        final private string $detail,
        final private string $iconUrl,
        final private int $bronzeCount,
        final private int $silverCount,
        final private int $goldCount,
        final private int $platinumCount,
        final private bool $usesPlayStation5Assets,
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
        return $this->id === TrophyGroupId::Default->value;
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
