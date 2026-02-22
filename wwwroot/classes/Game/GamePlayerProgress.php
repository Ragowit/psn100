<?php

declare(strict_types=1);

final readonly class GamePlayerProgress
{
    public function __construct(
        private string $npCommunicationId,
        private string $accountId,
        private int $bronze,
        private int $silver,
        private int $gold,
        private int $platinum,
        private int $progress,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            npCommunicationId: (string) ($row['np_communication_id'] ?? ''),
            accountId: (string) ($row['account_id'] ?? ''),
            bronze: (int) ($row['bronze'] ?? 0),
            silver: (int) ($row['silver'] ?? 0),
            gold: (int) ($row['gold'] ?? 0),
            platinum: (int) ($row['platinum'] ?? 0),
            progress: (int) ($row['progress'] ?? 0),
        );
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getBronzeCount(): int
    {
        return $this->bronze;
    }

    public function getSilverCount(): int
    {
        return $this->silver;
    }

    public function getGoldCount(): int
    {
        return $this->gold;
    }

    public function getPlatinumCount(): int
    {
        return $this->platinum;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function isCompleted(): bool
    {
        return $this->progress >= 100;
    }
}
