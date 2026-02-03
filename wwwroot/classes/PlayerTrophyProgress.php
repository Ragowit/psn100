<?php

declare(strict_types=1);

readonly class PlayerTrophyProgress
{
    public function __construct(
        private ?string $earnedDate,
        private ?string $progress,
        private bool $earned
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?string $progressTargetValue): self
    {
        $earned = ((int) ($data['earned'] ?? 0)) === 1;
        $progress = isset($data['progress']) ? (string) $data['progress'] : null;

        if ($earned && $progressTargetValue !== null) {
            $progress = $progressTargetValue;
        }

        $earnedDate = isset($data['earned_date']) ? (string) $data['earned_date'] : null;

        return new self($earnedDate, $progress, $earned);
    }

    public function wasEarned(): bool
    {
        return $this->earned;
    }

    public function getEarnedDate(): ?string
    {
        return $this->earnedDate;
    }

    public function getProgress(): ?string
    {
        return $this->progress;
    }
}
