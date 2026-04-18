<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayStationClientMode.php';

final readonly class PlayStationClientModeConfig
{
    public function __construct(private PlayStationClientMode $mode)
    {
    }

    public static function fromEnvironment(array $environment = []): self
    {
        return new self(
            PlayStationClientMode::fromEnvironmentValue(
                $environment['PSN_CLIENT_MODE'] ?? getenv('PSN_CLIENT_MODE')
            )
        );
    }

    public function getMode(): PlayStationClientMode
    {
        return $this->mode;
    }
}
