<?php

declare(strict_types=1);

namespace PsnApi;

final class UserTrophySummary
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function level(): int
    {
        return (int) ($this->data['trophyLevel'] ?? 0);
    }

    public function progress(): int
    {
        return (int) ($this->data['progress'] ?? 0);
    }

    public function points(): int
    {
        return (int) ($this->data['trophyPoint'] ?? 0);
    }

    public function platinum(): int
    {
        return (int) ($this->data['earnedTrophies']['platinum'] ?? 0);
    }

    public function gold(): int
    {
        return (int) ($this->data['earnedTrophies']['gold'] ?? 0);
    }

    public function silver(): int
    {
        return (int) ($this->data['earnedTrophies']['silver'] ?? 0);
    }

    public function bronze(): int
    {
        return (int) ($this->data['earnedTrophies']['bronze'] ?? 0);
    }
}
