<?php

declare(strict_types=1);

require_once __DIR__ . '/../CommaSeparatedValues.php';

final readonly class GameDetails
{
    /**
     * @param int[] $obsoleteGameIds
     */
    private function __construct(
        private int $id,
        private string $name,
        private string $npCommunicationId,
        private ?string $parentNpCommunicationId,
        private ?int $psnprofilesId,
        private string $platform,
        private string $iconUrl,
        private string $setVersion,
        private ?string $region,
        private ?string $message,
        private int $platinum,
        private int $gold,
        private int $silver,
        private int $bronze,
        private int $ownersCompleted,
        private int $owners,
        private string $difficulty,
        private int $status,
        private int $rarityPoints,
        private int $inGameRarityPoints,
        private array $obsoleteGameIds,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int) ($row['id'] ?? 0),
            (string) ($row['name'] ?? ''),
            (string) ($row['np_communication_id'] ?? ''),
            isset($row['parent_np_communication_id'])
                ? self::toNullableString($row['parent_np_communication_id'])
                : null,
            self::toNullableInt($row['psnprofiles_id'] ?? null),
            (string) ($row['platform'] ?? ''),
            (string) ($row['icon_url'] ?? ''),
            (string) ($row['set_version'] ?? ''),
            self::toNullableString($row['region'] ?? null),
            self::toNullableString($row['message'] ?? null),
            (int) ($row['platinum'] ?? 0),
            (int) ($row['gold'] ?? 0),
            (int) ($row['silver'] ?? 0),
            (int) ($row['bronze'] ?? 0),
            (int) ($row['owners_completed'] ?? 0),
            (int) ($row['owners'] ?? 0),
            (string) ($row['difficulty'] ?? '0'),
            (int) ($row['status'] ?? 0),
            (int) ($row['rarity_points'] ?? 0),
            (int) ($row['in_game_rarity_points'] ?? 0),
            self::parseObsoleteIds($row['obsolete_ids'] ?? null),
        );
    }

    private static function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;

        return $stringValue === '' ? null : $stringValue;
    }

    private static function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $stringValue = (string) $value;

        return ctype_digit($stringValue) ? (int) $stringValue : null;
    }

    /**
     * @return int[]
     */
    private static function parseObsoleteIds(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return CommaSeparatedValues::parseTrimmed($value)
            |> (fn(array $segments): array => array_filter($segments, ctype_digit(...)))
            |> (fn(array $segments): array => array_map(intval(...), $segments))
            |> array_unique(...)
            |> array_values(...);
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

    public function getPsnprofilesId(): ?int
    {
        return $this->psnprofilesId;
    }

    public function hasPsnprofilesId(): bool
    {
        return $this->psnprofilesId !== null;
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

    public function getInGameRarityPoints(): int
    {
        return $this->inGameRarityPoints;
    }

    /**
     * @return int[]
     */
    public function getObsoleteGameIds(): array
    {
        return $this->obsoleteGameIds;
    }

    public function hasObsoleteReplacements(): bool
    {
        return $this->obsoleteGameIds !== [];
    }
}
