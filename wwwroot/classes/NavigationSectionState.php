<?php

declare(strict_types=1);

require_once __DIR__ . '/NavigationSection.php';

final readonly class NavigationSectionState
{
    public function __construct(private NavigationSection $section, private bool $active)
    {
    }

    public function getSection(): NavigationSection
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
