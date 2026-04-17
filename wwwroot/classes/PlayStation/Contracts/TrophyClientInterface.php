<?php

declare(strict_types=1);

interface TrophyClientInterface
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $headers
     */
    public function requestTrophyEndpoint(string $path, array $query = [], array $headers = []): mixed;
}
