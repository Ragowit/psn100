<?php

declare(strict_types=1);

require_once __DIR__ . '/../CommaSeparatedValues.php';
require_once __DIR__ . '/../HistoryIconType.php';

abstract readonly class HomepageItem
{
    private const string MISSING_PS5_ICON = '/img/missing-ps5-game-and-trophy.png';
    private const string MISSING_PS4_ICON = '/img/missing-ps4-game.png';

    private HistoryIconType $iconType;

    protected function __construct(
        private string $iconUrl,
        private string $platform,
        HistoryIconType $iconType,
    ) {
        $this->iconType = $iconType;
    }

    public function getIconPath(): string
    {
        if ($this->iconUrl === '' || $this->iconUrl === '.png') {
            return $this->isPs5Title() ? self::MISSING_PS5_ICON : self::MISSING_PS4_ICON;
        }

        return '/img/' . $this->iconType->value . '/' . $this->iconUrl;
    }

    /**
     * @return string[]
     */
    public function getPlatforms(): array
    {
        return CommaSeparatedValues::parseTrimmed($this->platform);
    }

    private function isPs5Title(): bool
    {
        return str_contains($this->platform, 'PS5') || str_contains($this->platform, 'PSVR2');
    }
}
