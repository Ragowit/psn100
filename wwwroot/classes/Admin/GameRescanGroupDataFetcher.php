<?php

declare(strict_types=1);

use Tustin\PlayStation\Client;

interface GameRescanGroupDataFetcher
{
    /**
     * @return array<int, array{group: PsnTrophyGroupApiAdapter, trophies: array<int, PsnTrophyApiAdapter>}>
     */
    public function fetchGroupData(Client $client, string $npCommunicationId): array;
}
