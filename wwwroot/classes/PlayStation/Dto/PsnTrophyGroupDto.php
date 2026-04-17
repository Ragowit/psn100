<?php

declare(strict_types=1);

final class PsnTrophyGroupDto
{
    /**
     * @param array<int, PsnTrophyDto> $trophies
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $detail,
        private readonly string $iconUrl,
        private readonly array $trophies
    ) {
    }

    public function id(): string { return $this->id; }
    public function name(): string { return $this->name; }
    public function detail(): string { return $this->detail; }
    public function iconUrl(): string { return $this->iconUrl; }

    /**
     * @return array<int, PsnTrophyDto>
     */
    public function trophies(): array
    {
        return $this->trophies;
    }
}
