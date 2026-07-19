<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnTrophyTypeApiAdapter.php';

final readonly class PsnTrophyApiAdapter
{
    /**
     * @param array<string, mixed> $rawTrophy
     */
    public function __construct(private array $rawTrophy)
    {
    }

    public function id(): int
    {
        return (int) ($this->rawTrophy['trophyId'] ?? 0);
    }

    public function hidden(): bool
    {
        return (bool) ($this->rawTrophy['trophyHidden'] ?? false);
    }

    public function type(): PsnTrophyTypeApiAdapter
    {
        return new PsnTrophyTypeApiAdapter((string) ($this->rawTrophy['trophyType'] ?? 'bronze'));
    }

    public function name(): string
    {
        return (string) ($this->rawTrophy['trophyName'] ?? '');
    }

    public function detail(): string
    {
        return (string) ($this->rawTrophy['trophyDetail'] ?? '');
    }

    public function iconUrl(): string
    {
        return (string) ($this->rawTrophy['trophyIconUrl'] ?? '');
    }

    public function progressTargetValue(): string
    {
        $value = $this->rawTrophy['trophyProgressTargetValue'] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    public function rewardName(): string
    {
        return (string) ($this->rawTrophy['trophyRewardName'] ?? '');
    }

    public function rewardImageUrl(): ?string
    {
        $rewardImageUrl = $this->rawTrophy['trophyRewardImageUrl'] ?? null;
        if (!is_string($rewardImageUrl) || $rewardImageUrl === '') {
            return null;
        }

        return $rewardImageUrl;
    }
}
