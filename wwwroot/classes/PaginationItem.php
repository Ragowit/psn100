<?php

declare(strict_types=1);

final readonly class PaginationItem
{
    private function __construct(
        private ?int $page,
        private string $label,
        private bool $active = false,
        private bool $disabled = false,
        private ?string $ariaLabel = null,
    ) {
    }

    public static function forPage(int $page, string $label): self
    {
        return new self($page, $label);
    }

    public static function ellipsis(): self
    {
        return new self(null, '...', disabled: true);
    }

    public function markAsActive(): self
    {
        return new self(
            page: $this->page,
            label: $this->label,
            active: true,
            disabled: $this->disabled,
            ariaLabel: $this->ariaLabel,
        );
    }

    public function markAsDisabled(): self
    {
        return new self(
            page: $this->page,
            label: $this->label,
            active: $this->active,
            disabled: true,
            ariaLabel: $this->ariaLabel,
        );
    }

    public function setAriaLabel(?string $ariaLabel): self
    {
        return new self(
            page: $this->page,
            label: $this->label,
            active: $this->active,
            disabled: $this->disabled,
            ariaLabel: $ariaLabel,
        );
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

