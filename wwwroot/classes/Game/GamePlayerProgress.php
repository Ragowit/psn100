<?php

declare(strict_types=1);

class GamePlayerProgress
{
    private string $npCommunicationId;

    private string $accountId;

    private int $bronze;

    private int $silver;

    private int $gold;

    private int $platinum;

    private int $progress;

    private function __construct()
    {
        $this->npCommunicationId = '';
        $this->accountId = '';
        $this->bronze = 0;
        $this->silver = 0;
        $this->gold = 0;
        $this->platinum = 0;
        $this->progress = 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $player = new self();
        $player->npCommunicationId = (string) ($row['np_communication_id'] ?? '');
        $player->accountId = (string) ($row['account_id'] ?? '');
        $player->bronze = (int) ($row['bronze'] ?? 0);
        $player->silver = (int) ($row['silver'] ?? 0);
        $player->gold = (int) ($row['gold'] ?? 0);
        $player->platinum = (int) ($row['platinum'] ?? 0);
        $player->progress = (int) ($row['progress'] ?? 0);

        return $player;
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
