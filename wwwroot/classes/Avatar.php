<?php

class Avatar
{
    private string $url;

    private int $count;

    public function __construct(string $url, int $count)
    {
        $this->url = $url;
        $this->count = $count;
    }

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
