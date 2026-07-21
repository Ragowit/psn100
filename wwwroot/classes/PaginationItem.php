<?php

declare(strict_types=1);

require_once __DIR__ . '/Html.php';

final readonly class PaginationItem
{
    private function __construct(
        final private ?int $page,
        final private string $label,
        final private bool $active = false,
        final private bool $disabled = false,
        final private ?string $ariaLabel = null,
    ) {
    }

    #[\NoDiscard]
    public static function forPage(int $page, string $label): self
    {
        return new self($page, $label);
    }

    #[\NoDiscard]
    public static function ellipsis(): self
    {
        return new self(null, '...', disabled: true);
    }

    #[\NoDiscard]
    public function markAsActive(): self
    {
        return clone($this, ['active' => true]);
    }

    #[\NoDiscard]
    public function markAsDisabled(): self
    {
        return clone($this, ['disabled' => true]);
    }

    #[\NoDiscard]
    public function setAriaLabel(?string $ariaLabel): self
    {
        return clone($this, ['ariaLabel' => $ariaLabel]);
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
            $attributes[] = 'aria-label="' . Html::escape($this->ariaLabel) . '"';
        }

        if ($this->disabled) {
            $attributes[] = 'tabindex="-1"';
            $attributes[] = 'aria-disabled="true"';
        }

        $attributesString = $attributes === []
            ? ''
            : ' ' . implode(' ', $attributes);

        $label = Html::escape($this->label);
        $href = Html::escape($href);
        $classAttribute = Html::escape(implode(' ', $classNames));

        return '<li class="' . $classAttribute . '"><a class="page-link" href="' . $href . '"'
            . $attributesString . '>' . $label . '</a></li>';
    }
}

