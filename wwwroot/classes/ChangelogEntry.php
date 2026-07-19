<?php

declare(strict_types=1);

require_once __DIR__ . '/CommaSeparatedValues.php';

enum ChangelogEntryType: string
{
    case GAME_CLONE = 'GAME_CLONE';
    case GAME_COPY = 'GAME_COPY';
    case GAME_DELETE = 'GAME_DELETE';
    case GAME_DELISTED = 'GAME_DELISTED';
    case GAME_DELISTED_AND_OBSOLETE = 'GAME_DELISTED_AND_OBSOLETE';
    case GAME_HISTORY_SNAPSHOT = 'GAME_HISTORY_SNAPSHOT';
    case GAME_MERGE = 'GAME_MERGE';
    case GAME_NORMAL = 'GAME_NORMAL';
    case GAME_OBSOLETE = 'GAME_OBSOLETE';
    case GAME_OBTAINABLE = 'GAME_OBTAINABLE';
    case GAME_RESCAN = 'GAME_RESCAN';
    case GAME_RESET = 'GAME_RESET';
    case GAME_UNOBTAINABLE = 'GAME_UNOBTAINABLE';
    case GAME_UPDATE = 'GAME_UPDATE';
    case GAME_VERSION = 'GAME_VERSION';
    case UNKNOWN = 'UNKNOWN';
}

final readonly class ChangelogEntry
{
    /**
     * @param array<int, string> $param1Platforms
     * @param array<int, string> $param2Platforms
     */
    private function __construct(
        final private DateTimeImmutable $time,
        final private string $changeTypeValue,
        final private ChangelogEntryType $changeType,
        final private ?int $param1Id,
        final private ?string $param1Name,
        final private array $param1Platforms,
        final private ?string $param1Region,
        final private ?int $param2Id,
        final private ?string $param2Name,
        final private array $param2Platforms,
        final private ?string $param2Region,
        final private ?string $extra
    ) {}

    /**
     * @param array<string, mixed> $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): self
    {
        return new self(
            self::createDateTime((string) ($row['time'] ?? 'now')),
            $changeTypeValue = (string) ($row['change_type'] ?? ''),
            self::normalizeChangeType($changeTypeValue),
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

    private static function normalizeChangeType(string $changeType): ChangelogEntryType
    {
        return ChangelogEntryType::tryFrom($changeType) ?? ChangelogEntryType::UNKNOWN;
    }

    /**
     * @return array<int, string>
     */
    private static function normalizePlatforms(?string $platforms): array
    {
        if ($platforms === null) {
            return [];
        }

        return CommaSeparatedValues::parseTrimmed($platforms);
    }

    public function getTime(): DateTimeImmutable
    {
        return $this->time;
    }

    public function getChangeType(): ChangelogEntryType
    {
        return $this->changeType;
    }

    public function getChangeTypeValue(): string
    {
        return $this->changeTypeValue;
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
