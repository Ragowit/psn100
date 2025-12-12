<?php

declare(strict_types=1);

final readonly class GameStatusBadge
{
    public function __construct(
        private string $label,
        private string $tooltip,
        private string $cssClass = 'badge rounded-pill text-bg-warning'
    ) {
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTooltip(): string
    {
        return $this->tooltip;
    }

    public function getCssClass(): string
    {
        return $this->cssClass;
    }
}
