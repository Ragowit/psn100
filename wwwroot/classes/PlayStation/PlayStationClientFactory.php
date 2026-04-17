<?php

declare(strict_types=1);

require_once __DIR__ . '/Contracts/PlayStationClientFactoryInterface.php';
require_once __DIR__ . '/TustinPlayStationApiClient.php';

final class PlayStationClientFactory implements PlayStationClientFactoryInterface
{
    public function createClient(): PlayStationApiClientInterface
    {
        return new TustinPlayStationApiClient();
    }
}
