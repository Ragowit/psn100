<?php

declare(strict_types=1);

final readonly class NavigationSectionState
{
    public function __construct(private string $section, private bool $active)
    {
    }

    public function getSection(): string
    {
        return $this->section;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCssClass(): string
    {
        return $this->active ? ' active' : '';
    }
}
