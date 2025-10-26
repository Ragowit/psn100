<?php

declare(strict_types=1);

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

    public static function bootstrapCdn(string $version = '5.3.8'): self
    {
        $href = sprintf('https://cdn.jsdelivr.net/npm/bootstrap@%s/dist/css/bootstrap.min.css', $version);
        $integrity = 'sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB';
        $crossorigin = 'anonymous';

        return new self($href, 'stylesheet', $integrity, $crossorigin);
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
