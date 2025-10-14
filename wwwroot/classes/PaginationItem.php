<?php

declare(strict_types=1);

final class PaginationItem
{
    private ?int $page;

    private string $label;

    private bool $active = false;

    private bool $disabled = false;

    private ?string $ariaLabel = null;

    private function __construct(?int $page, string $label)
    {
        $this->page = $page;
        $this->label = $label;
    }

    public static function forPage(int $page, string $label): self
    {
        return new self($page, $label);
    }

    public static function ellipsis(): self
    {
        return (new self(null, '...'))->markAsDisabled();
    }

    public function markAsActive(): self
    {
        $this->active = true;

        return $this;
    }

    public function markAsDisabled(): self
    {
        $this->disabled = true;

        return $this;
    }

    public function setAriaLabel(?string $ariaLabel): self
    {
        $this->ariaLabel = $ariaLabel;

        return $this;
    }

    /**
     * @param callable(int):string $urlBuilder
     */
    public function render(callable $urlBuilder): string
    {
        $classNames = ['page-item'];

        if ($this->active) {
            $classNames[] = 'active';
        }

        if ($this->disabled) {
            $classNames[] = 'disabled';
        }

        $href = '#';

        if (!$this->disabled && $this->page !== null) {
            $href = (string) $urlBuilder($this->page);
        }

        $attributes = [];

        if ($this->active) {
            $attributes[] = 'aria-current="page"';
        }

        if ($this->ariaLabel !== null && $this->ariaLabel !== '') {
            $attributes[] = 'aria-label="' . htmlspecialchars($this->ariaLabel, ENT_QUOTES, 'UTF-8') . '"';
        }

        if ($this->disabled) {
            $attributes[] = 'tabindex="-1"';
            $attributes[] = 'aria-disabled="true"';
        }

        $attributesString = $attributes === []
            ? ''
            : ' ' . implode(' ', $attributes);

        $label = htmlspecialchars($this->label, ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $classAttribute = htmlspecialchars(implode(' ', $classNames), ENT_QUOTES, 'UTF-8');

        return '<li class="' . $classAttribute . '"><a class="page-link" href="' . $href . '"'
            . $attributesString . '>' . $label . '</a></li>';
    }
}

