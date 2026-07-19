<?php

declare(strict_types=1);

require_once __DIR__ . '/BootstrapAssets.php';

final readonly class MaintenancePageStylesheet
{
    private function __construct(
        final private string $href,
        final private string $rel = 'stylesheet',
        final private ?string $integrity = null,
        final private ?string $crossorigin = null,
    ) {
    }

    #[\NoDiscard]
    public static function create(string $href, string $rel = 'stylesheet', ?string $integrity = null, ?string $crossorigin = null): self
    {
        return new self($href, $rel, $integrity, $crossorigin);
    }

    #[\NoDiscard]
    public static function bootstrap(string $version = BootstrapAssets::VERSION): self
    {
        if ($version !== BootstrapAssets::VERSION) {
            throw new InvalidArgumentException(sprintf('Unsupported Bootstrap version: %s', $version));
        }

        return new self(BootstrapAssets::stylesheetUrl());
    }

    #[\Deprecated(message: 'Use bootstrap() for the self-hosted stylesheet.')]
    public static function bootstrapCdn(string $version = BootstrapAssets::VERSION): self
    {
        return self::bootstrap($version);
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function getRel(): string
    {
        return $this->rel;
    }

    public function getIntegrity(): ?string
    {
        return $this->integrity;
    }

    public function getCrossorigin(): ?string
    {
        return $this->crossorigin;
    }
}
