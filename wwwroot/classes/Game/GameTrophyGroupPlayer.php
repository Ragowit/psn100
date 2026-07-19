<?php

declare(strict_types=1);

final readonly class GameTrophyGroupPlayer
{
    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
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
        final private string $npCommunicationId,
        final private string $groupId,
        final private int $accountId,
        final private int $bronzeCount,
        final private int $silverCount,
        final private int $goldCount,
        final private int $platinumCount,
        final private int $progress,
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
