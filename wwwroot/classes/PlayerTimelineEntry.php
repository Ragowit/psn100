<?php

declare(strict_types=1);

final readonly class PlayerTimelineEntry
{
    private function __construct(
        private int $gameId,
        private string $name,
        private int $progress,
        private DateTimeImmutable $firstTrophyDate,
        private DateTimeImmutable $lastTrophyDate,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
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

    public function getStatusClass(DateTimeImmutable $today): string
    {
        if ($this->progress >= 100) {
            return 'completed';
        }

        $daysSince = (int) $this->lastTrophyDate->diff($today)->format('%r%a');
        if ($daysSince > 90) {
            return 'stalled';
        }

        return 'playing';
    }
}
