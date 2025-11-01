<?php

declare(strict_types=1);


class ChangelogEntry
{
    public const TYPE_GAME_CLONE = 'GAME_CLONE';
    public const TYPE_GAME_COPY = 'GAME_COPY';
    public const TYPE_GAME_DELETE = 'GAME_DELETE';
    public const TYPE_GAME_DELISTED = 'GAME_DELISTED';
    public const TYPE_GAME_DELISTED_AND_OBSOLETE = 'GAME_DELISTED_AND_OBSOLETE';
    public const TYPE_GAME_HISTORY_SNAPSHOT = 'GAME_HISTORY_SNAPSHOT';
    public const TYPE_GAME_MERGE = 'GAME_MERGE';
    public const TYPE_GAME_NORMAL = 'GAME_NORMAL';
    public const TYPE_GAME_OBSOLETE = 'GAME_OBSOLETE';
    public const TYPE_GAME_OBTAINABLE = 'GAME_OBTAINABLE';
    public const TYPE_GAME_RESCAN = 'GAME_RESCAN';
    public const TYPE_GAME_RESET = 'GAME_RESET';
    public const TYPE_GAME_UNOBTAINABLE = 'GAME_UNOBTAINABLE';
    public const TYPE_GAME_UPDATE = 'GAME_UPDATE';
    public const TYPE_GAME_VERSION = 'GAME_VERSION';

    private DateTimeImmutable $time;
    private string $changeType;
    private ?int $param1Id;
    private ?string $param1Name;
    /**
     * @var array<int, string>
     */
    private array $param1Platforms;
    private ?string $param1Region;
    private ?int $param2Id;
    private ?string $param2Name;
    /**
     * @var array<int, string>
     */
    private array $param2Platforms;
    private ?string $param2Region;
    private ?string $extra;

    /**
     * @param array<int, string> $param1Platforms
     * @param array<int, string> $param2Platforms
     */
    private function __construct(
        DateTimeImmutable $time,
        string $changeType,
        ?int $param1Id,
        ?string $param1Name,
        array $param1Platforms,
        ?string $param1Region,
        ?int $param2Id,
        ?string $param2Name,
        array $param2Platforms,
        ?string $param2Region,
        ?string $extra
    ) {
        $this->time = $time;
        $this->changeType = $changeType;
        $this->param1Id = $param1Id;
        $this->param1Name = $param1Name;
        $this->param1Platforms = $param1Platforms;
        $this->param1Region = $param1Region;
        $this->param2Id = $param2Id;
        $this->param2Name = $param2Name;
        $this->param2Platforms = $param2Platforms;
        $this->param2Region = $param2Region;
        $this->extra = $extra;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            self::createDateTime(isset($row['time']) ? (string) $row['time'] : 'now'),
            isset($row['change_type']) ? (string) $row['change_type'] : '',
            isset($row['param_1']) ? (int) $row['param_1'] : null,
            isset($row['param_1_name']) ? (string) $row['param_1_name'] : null,
            self::normalizePlatforms($row['param_1_platform'] ?? null),
            isset($row['param_1_region']) ? (string) $row['param_1_region'] : null,
            isset($row['param_2']) ? (int) $row['param_2'] : null,
            isset($row['param_2_name']) ? (string) $row['param_2_name'] : null,
            self::normalizePlatforms($row['param_2_platform'] ?? null),
            isset($row['param_2_region']) ? (string) $row['param_2_region'] : null,
            isset($row['extra']) ? (string) $row['extra'] : null
        );
    }

    private static function createDateTime(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return new DateTimeImmutable('@0');
        }
    }

    /**
     * @return array<int, string>
     */
    private static function normalizePlatforms(?string $platforms): array
    {
        if ($platforms === null || $platforms === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $platforms));
        $parts = array_filter(
            $parts,
            static fn(string $platform): bool => $platform !== ''
        );

        return array_values($parts);
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    public function getChangeType(): string
    {
        return $this->changeType;
    }

    public function getParam1Id(): ?int
    {
        return $this->param1Id;
    }

    public function getParam1Name(): ?string
    {
        return $this->param1Name;
    }

    /**
     * @return array<int, string>
     */
    public function getParam1Platforms(): array
    {
        return $this->param1Platforms;
    }

    public function getParam1Region(): ?string
    {
        return $this->param1Region;
    }

    public function getParam2Id(): ?int
    {
        return $this->param2Id;
    }

    public function getParam2Name(): ?string
    {
        return $this->param2Name;
    }

    /**
     * @return array<int, string>
     */
    public function getParam2Platforms(): array
    {
        return $this->param2Platforms;
    }

    public function getParam2Region(): ?string
    {
        return $this->param2Region;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }
}
