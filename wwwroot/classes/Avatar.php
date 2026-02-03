<?php

declare(strict_types=1);

readonly class Avatar
{
    public function __construct(
        private string $url,
        private int $count
    ) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getPlayerLabel(): string
    {
        return $this->count === 1 ? 'player' : 'players';
    }
}
