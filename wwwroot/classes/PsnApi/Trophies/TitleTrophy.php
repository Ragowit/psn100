<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Trophies;

final class TitleTrophy
{
    /** @var array<string, mixed> */
    private array $data;

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
        return new TrophyType((string) ($this->data['trophyType'] ?? 'unknown'));
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

    public function progressTargetValue(): string
    {
        $value = $this->data['trophyProgressTargetValue'] ?? '';

        return $value === null ? '' : (string) $value;
    }

    public function rewardName(): string
    {
        $value = $this->data['trophyRewardName'] ?? '';

        return $value === null ? '' : (string) $value;
    }

    public function rewardImageUrl(): ?string
    {
        $value = $this->data['trophyRewardImageUrl'] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    public function groupId(): string
    {
        return (string) ($this->data['trophyGroupId'] ?? 'default');
    }
}
