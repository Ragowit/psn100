<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerTimelineStatus.php';

final readonly class PlayerTimelineEntry
{
    private function __construct(
        final private int $gameId,
        final private string $name,
        final private int $progress,
        final private DateTimeImmutable $firstTrophyDate,
        final private DateTimeImmutable $lastTrophyDate,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    #[\NoDiscard]
    public static function fromRow(array $row): self
    {
        return new self(
            gameId: (int) ($row['game_id'] ?? 0),
            name: (string) ($row['name'] ?? ''),
            progress: (int) ($row['progress'] ?? 0),
            firstTrophyDate: new DateTimeImmutable((string) $row['first_trophy']),
            lastTrophyDate: new DateTimeImmutable((string) $row['last_trophy']),
        );
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function getFirstTrophyDate(): DateTimeImmutable
    {
        return $this->firstTrophyDate;
    }

    public function getLastTrophyDate(): DateTimeImmutable
    {
        return $this->lastTrophyDate;
    }

    #[\NoDiscard]
    public function getStatus(DateTimeImmutable $today): PlayerTimelineStatus
    {
        if ($this->progress >= 100) {
            return PlayerTimelineStatus::Completed;
        }

        $daysSince = (int) $this->lastTrophyDate->diff($today)->format('%r%a');
        if ($daysSince > 90) {
            return PlayerTimelineStatus::Stalled;
        }

        return PlayerTimelineStatus::Playing;
    }

    public function getStatusClass(DateTimeImmutable $today): string
    {
        return $this->getStatus($today)->cssClass();
    }
}
