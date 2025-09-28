<?php

declare(strict_types=1);

final class GameStatusBadge
{
    private string $label;

    private string $tooltip;

    private string $cssClass;

    public function __construct(string $label, string $tooltip, string $cssClass = 'badge rounded-pill text-bg-warning')
    {
        $this->label = $label;
        $this->tooltip = $tooltip;
        $this->cssClass = $cssClass;
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
