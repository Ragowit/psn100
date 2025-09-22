<?php

declare(strict_types=1);

abstract class HomepageItem
{
    private const MISSING_PS5_ICON = '/img/missing-ps5-game-and-trophy.png';
    private const MISSING_PS4_ICON = '/img/missing-ps4-game.png';

    private string $iconUrl;

    private string $platform;

    private string $iconDirectory;

    protected function __construct(string $iconUrl, string $platform, string $iconDirectory)
    {
        $this->iconUrl = $iconUrl;
        $this->platform = $platform;
        $this->iconDirectory = trim($iconDirectory, '/');
    }

    public function getIconPath(): string
    {
        if ($this->iconUrl === '' || $this->iconUrl === '.png') {
            return $this->isPs5Title() ? self::MISSING_PS5_ICON : self::MISSING_PS4_ICON;
        }

        return '/img/' . $this->iconDirectory . '/' . $this->iconUrl;
    }

    /**
     * @return string[]
     */
    public function getPlatforms(): array
    {
        if ($this->platform === '') {
            return [];
        }

        $platforms = array_map('trim', explode(',', $this->platform));

        $platforms = array_filter(
            $platforms,
            static fn(string $value): bool => $value !== ''
        );

        return array_values($platforms);
    }

    private function isPs5Title(): bool
    {
        return str_contains($this->platform, 'PS5') || str_contains($this->platform, 'PSVR2');
    }
}
