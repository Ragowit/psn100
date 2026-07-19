<?php

declare(strict_types=1);

require_once __DIR__ . '/Html.php';

final readonly class TrophyRarity
{
    public function __construct(
        final private ?string $percentage,
        final private string $label,
        final private ?string $cssClass,
        final private bool $unobtainable,
    ) {
    }

    public function getPercentage(): ?string
    {
        return $this->percentage;
    }

    public function hasPercentage(): bool
    {
        return $this->percentage !== null && $this->percentage !== '';
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function isUnobtainable(): bool
    {
        return $this->unobtainable;
    }

    public function renderSpan(string $separator = '<br>', bool $includePercentWhenUnobtainable = false): string
    {
        $parts = [];

        if (!$this->unobtainable || $includePercentWhenUnobtainable) {
            if ($this->hasPercentage()) {
                $parts[] = Html::escape($this->percentage) . '%';
            }
        }

        $parts[] = Html::escape($this->label);

        $classAttribute = '';
        if ($this->cssClass !== null && $this->cssClass !== '') {
            $classAttribute = ' class="' . Html::escape($this->cssClass) . '"';
        }

        return '<span' . $classAttribute . '>' . implode($separator, $parts) . '</span>';
    }
}
