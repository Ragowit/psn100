<?php

declare(strict_types=1);

final class AdminLayoutOptions
{
    private bool $showBackLink;

    private string $containerClass;

    public function __construct(bool $showBackLink = true, string $containerClass = 'p-4')
    {
        $this->showBackLink = $showBackLink;
        $this->containerClass = $this->normaliseContainerClass($containerClass);
    }

    public static function create(): self
    {
        return new self();
    }

    public function withBackLink(bool $showBackLink): self
    {
        $clone = clone $this;
        $clone->showBackLink = $showBackLink;

        return $clone;
    }

    public function withContainerClass(string $containerClass): self
    {
        $clone = clone $this;
        $clone->containerClass = $this->normaliseContainerClass($containerClass);

        return $clone;
    }

    public function shouldShowBackLink(): bool
    {
        return $this->showBackLink;
    }

    public function getContainerClass(): string
    {
        return $this->containerClass;
    }

    private function normaliseContainerClass(string $containerClass): string
    {
        $trimmed = trim($containerClass);

        return $trimmed === '' ? 'p-4' : $trimmed;
    }
}
