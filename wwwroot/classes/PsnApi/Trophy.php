<?php

declare(strict_types=1);

namespace PsnApi;

final class Trophy
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

    public function id(): int
    {
        return (int) ($this->data['trophyId'] ?? 0);
    }

    public function hidden(): bool
    {
        return (bool) ($this->data['trophyHidden'] ?? false);
    }

    public function type(): TrophyType
    {
        return new TrophyType((string) ($this->data['trophyType'] ?? '')); 
    }

    public function name(): string
    {
        return (string) ($this->data['trophyName'] ?? '');
    }

    public function detail(): string
    {
        return (string) ($this->data['trophyDetail'] ?? '');
    }

    public function iconUrl(): string
    {
        return (string) ($this->data['trophyIconUrl'] ?? '');
    }

    public function progressTargetValue(): ?string
    {
        $value = $this->data['trophyProgressTargetValue'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function rewardName(): ?string
    {
        $value = $this->data['trophyRewardName'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function rewardImageUrl(): ?string
    {
        $value = $this->data['trophyRewardImageUrl'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    public function earned(): int
    {
        $value = $this->data['earned'] ?? false;
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    public function earnedDateTime(): string
    {
        return (string) ($this->data['earnedDateTime'] ?? '');
    }

    public function progress(): string
    {
        return (string) ($this->data['progress'] ?? '');
    }
}
