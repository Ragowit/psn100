<?php

declare(strict_types=1);

final class GameTrophyGroupPlayer
{
    private string $npCommunicationId;

    private string $groupId;

    private int $accountId;

    private int $bronzeCount;

    private int $silverCount;

    private int $goldCount;

    private int $platinumCount;

    private int $progress;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->npCommunicationId = (string) ($data['np_communication_id'] ?? '');
        $this->groupId = (string) ($data['group_id'] ?? '');
        $this->accountId = isset($data['account_id']) ? (int) $data['account_id'] : 0;
        $this->bronzeCount = isset($data['bronze']) ? (int) $data['bronze'] : 0;
        $this->silverCount = isset($data['silver']) ? (int) $data['silver'] : 0;
        $this->goldCount = isset($data['gold']) ? (int) $data['gold'] : 0;
        $this->platinumCount = isset($data['platinum']) ? (int) $data['platinum'] : 0;
        $this->progress = isset($data['progress']) ? (int) $data['progress'] : 0;
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
