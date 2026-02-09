<?php

declare(strict_types=1);

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
     * @var array<string, string>
     */
    private const array PLATFORM_LABELS = [
        'pc' => 'PC',
        'ps3' => 'PS3',
        'ps4' => 'PS4',
        'ps5' => 'PS5',
        'psvita' => 'PSVITA',
        'psvr' => 'PSVR',
        'psvr2' => 'PSVR2',
    ];

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
                static fn (string $key): PlayerPlatformFilterOption => new PlayerPlatformFilterOption(
                    $key,
                    self::PLATFORM_LABELS[$key],
                    (bool) $selectionCallback($key)
                ),
                array_keys(self::PLATFORM_LABELS)
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
