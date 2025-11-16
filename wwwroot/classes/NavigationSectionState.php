<?php

declare(strict_types=1);

final class NavigationSectionState
{
    private string $section;

    private bool $active;

    public function __construct(string $section, bool $active)
    {
        $this->section = $section;
        $this->active = $active;
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
