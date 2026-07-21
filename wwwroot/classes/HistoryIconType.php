<?php

declare(strict_types=1);

enum HistoryIconType: string
{
    case Group = 'group';
    case Title = 'title';
    case Trophy = 'trophy';

    public function usesGameMissingAsset(): bool
    {
        return $this === self::Group || $this === self::Title;
    }

    /**
     * @return array{objectFit: string, directory: string, height: float}
     */
    public function display(): array
    {
        return match ($this) {
            self::Group => ['objectFit' => 'object-fit-cover', 'directory' => 'group', 'height' => 3.5],
            self::Title => ['objectFit' => 'object-fit-scale', 'directory' => 'title', 'height' => 5.5],
            self::Trophy => ['objectFit' => 'object-fit-scale', 'directory' => 'trophy', 'height' => 3.5],
        };
    }
}
