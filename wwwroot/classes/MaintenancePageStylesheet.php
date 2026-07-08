<?php

declare(strict_types=1);

require_once __DIR__ . '/BootstrapAssets.php';

final class MaintenancePageStylesheet
{
    private string $href;

    private string $rel;

    private ?string $integrity;

    private ?string $crossorigin;

    private function __construct(string $href, string $rel = 'stylesheet', ?string $integrity = null, ?string $crossorigin = null)
    {
        $this->href = $href;
        $this->rel = $rel;
        $this->integrity = $integrity;
        $this->crossorigin = $crossorigin;
    }

    public static function create(string $href, string $rel = 'stylesheet', ?string $integrity = null, ?string $crossorigin = null): self
    {
        return new self($href, $rel, $integrity, $crossorigin);
    }

    public static function bootstrap(string $version = BootstrapAssets::VERSION): self
    {
        if ($version !== BootstrapAssets::VERSION) {
            throw new InvalidArgumentException(sprintf('Unsupported Bootstrap version: %s', $version));
        }

        return new self(BootstrapAssets::stylesheetUrl());
    }

    /**
     * @deprecated Use bootstrap() for the self-hosted stylesheet.
     */
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
