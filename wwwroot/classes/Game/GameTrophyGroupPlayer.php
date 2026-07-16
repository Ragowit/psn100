<?php

declare(strict_types=1);

final readonly class GameTrophyGroupPlayer
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['np_communication_id'] ?? ''),
            (string) ($data['group_id'] ?? ''),
            (int) ($data['account_id'] ?? 0),
            (int) ($data['bronze'] ?? 0),
            (int) ($data['silver'] ?? 0),
            (int) ($data['gold'] ?? 0),
            (int) ($data['platinum'] ?? 0),
            (int) ($data['progress'] ?? 0),
        );
    }

    private function __construct(
        private string $npCommunicationId,
        private string $groupId,
        private int $accountId,
        private int $bronzeCount,
        private int $silverCount,
        private int $goldCount,
        private int $platinumCount,
        private int $progress,
    ) {
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
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

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function isComplete(): bool
    {
        return $this->progress >= 100;
    }
}
