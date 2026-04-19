<?php

declare(strict_types=1);

require_once __DIR__ . '/Contracts/PlayStationClientFactoryInterface.php';
require_once __DIR__ . '/TustinPlayStationApiClient.php';
require_once __DIR__ . '/../PsnClientMode.php';

final class PlayStationClientFactory implements PlayStationClientFactoryInterface
{
    public function createClient(): PlayStationApiClientInterface
    {
        $mode = PsnClientMode::forService('playstation_client_factory');

        return match ($mode->value()) {
            PsnClientMode::LEGACY,
            PsnClientMode::SHADOW,
            PsnClientMode::NEW => new TustinPlayStationApiClient(),
        };
    }
}
