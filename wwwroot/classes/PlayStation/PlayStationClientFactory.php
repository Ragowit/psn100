<?php

declare(strict_types=1);

require_once __DIR__ . '/Contracts/PlayStationClientFactoryInterface.php';
require_once __DIR__ . '/NativePlayStationApiClient.php';

final class PlayStationClientFactory implements PlayStationClientFactoryInterface
{
    public function createClient(): PlayStationApiClientInterface
    {
        return new NativePlayStationApiClient();
    }
}
