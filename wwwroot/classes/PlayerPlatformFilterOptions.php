<?php

declare(strict_types=1);

final class PlayerPlatformFilterOption
{
    private string $key;

    private string $label;

    private bool $selected;

    public function __construct(string $key, string $label, bool $selected)
    {
        $this->key = $key;
        $this->label = $label;
        $this->selected = $selected;
    }

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

final class PlayerPlatformFilterOptions
{
    /**
     * @var array<string, string>
     */
    private const PLATFORM_LABELS = [
        'pc' => 'PC',
        'ps3' => 'PS3',
        'ps4' => 'PS4',
        'ps5' => 'PS5',
        'psvita' => 'PSVITA',
        'psvr' => 'PSVR',
        'psvr2' => 'PSVR2',
    ];

    /**
     * @var PlayerPlatformFilterOption[]
     */
    private array $options;

    /**
     * @param PlayerPlatformFilterOption[] $options
     */
    private function __construct(array $options)
    {
        $this->options = $options;
    }

    /**
     * @param callable(string):bool $selectionCallback
     */
    public static function fromSelectionCallback(callable $selectionCallback): self
    {
        $options = [];

        foreach (self::PLATFORM_LABELS as $key => $label) {
            $options[] = new PlayerPlatformFilterOption(
                $key,
                $label,
                (bool) $selectionCallback($key)
            );
        }

        return new self($options);
    }

    /**
     * @return PlayerPlatformFilterOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}

