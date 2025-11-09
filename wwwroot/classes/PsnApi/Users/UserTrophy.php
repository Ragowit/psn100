<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

use Achievements\PsnApi\Trophies\TitleTrophy;
use Achievements\PsnApi\Trophies\TrophyType;

final class UserTrophy
{
    private TitleTrophy $metadata;

    /** @var array<string, mixed> */
    private array $userData;

    public function __construct(TitleTrophy $metadata, array $userData)
    {
        $this->metadata = $metadata;
        $this->userData = $userData;
    }

    public function id(): int
    {
        return $this->metadata->id();
    }

    public function hidden(): bool
    {
        return $this->metadata->hidden();
    }

    public function name(): string
    {
        return $this->metadata->name();
    }

    public function detail(): string
    {
        return $this->metadata->detail();
    }

    public function iconUrl(): string
    {
        return $this->metadata->iconUrl();
    }

    public function progressTargetValue(): ?string
    {
        return $this->metadata->progressTargetValue();
    }

    public function rewardName(): ?string
    {
        $rewardName = $this->userData['trophyRewardName'] ?? $this->metadata->rewardName();

        return $rewardName === null || $rewardName === '' ? null : (string) $rewardName;
    }

    public function rewardImageUrl(): ?string
    {
        $rewardImage = $this->userData['trophyRewardImageUrl'] ?? $this->metadata->rewardImageUrl();

        return $rewardImage === null || $rewardImage === '' ? null : (string) $rewardImage;
    }

    public function type(): TrophyType
    {
        return $this->metadata->type();
    }

    public function earned(): bool
    {
        return (bool) ($this->userData['earned'] ?? false);
    }

    public function earnedDateTime(): string
    {
        return (string) ($this->userData['earnedDateTime'] ?? '');
    }

    public function progress(): string
    {
        $progress = $this->userData['progress'] ?? null;

        if ($progress === null || $progress === '') {
            return '';
        }

        return (string) $progress;
    }

    public function earnedRate(): ?string
    {
        $value = $this->userData['trophyEarnedRate'] ?? null;

        return $value === null ? null : (string) $value;
    }
}
