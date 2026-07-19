<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';

readonly final class PlayerPlatformFilterOption
{
    public function __construct(
        private string $key,
        private string $label,
        private bool $selected
    ) {}

    public function getInputName(): string
    {
        return $this->key;
    }

    public function getInputId(): string
    {
        return 'filter' . strtoupper($this->key);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }
}

readonly final class PlayerPlatformFilterOptions
{
    /**
     * @param PlayerPlatformFilterOption[] $options
     */
    private function __construct(private array $options)
    {
    }

    /**
     * @param callable(string):bool $selectionCallback
     */
    public static function fromSelectionCallback(callable $selectionCallback): self
    {
        return new self(
            array_map(
                static fn (Platform $platform): PlayerPlatformFilterOption => new PlayerPlatformFilterOption(
                    $platform->value,
                    $platform->label(),
                    (bool) $selectionCallback($platform->value)
                ),
                Platform::cases()
            )
        );
    }

    /**
     * @return PlayerPlatformFilterOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
