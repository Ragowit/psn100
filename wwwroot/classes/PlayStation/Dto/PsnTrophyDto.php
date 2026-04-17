<?php

declare(strict_types=1);

final class PsnTrophyDto
{
    public function __construct(
        private readonly int $id,
        private readonly bool $hasValidId,
        private readonly bool $hidden,
        private readonly string $type,
        private readonly string $name,
        private readonly string $detail,
        private readonly string $iconUrl,
        private readonly string $earnedDateTime,
        private readonly string $progress,
        private readonly string $progressTargetValue,
        private readonly string $rewardName,
        private readonly ?string $rewardImageUrl
    ) {
    }

    public function id(): int { return $this->id; }
    public function hasValidId(): bool { return $this->hasValidId; }
    public function hidden(): bool { return $this->hidden; }
    public function type(): string { return $this->type; }
    public function name(): string { return $this->name; }
    public function detail(): string { return $this->detail; }
    public function iconUrl(): string { return $this->iconUrl; }
    public function earnedDateTime(): string { return $this->earnedDateTime; }
    public function progress(): string { return $this->progress; }
    public function progressTargetValue(): string { return $this->progressTargetValue; }
    public function rewardName(): string { return $this->rewardName; }
    public function rewardImageUrl(): ?string { return $this->rewardImageUrl; }
}
