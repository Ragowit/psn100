<?php

declare(strict_types=1);

require_once __DIR__ . '/../CommaSeparatedValues.php';
require_once __DIR__ . '/../HistoryIconType.php';
require_once __DIR__ . '/../Platform.php';

abstract readonly class HomepageItem
{
    private const string MISSING_PS5_ICON = '/img/missing-ps5-game-and-trophy.png';
    private const string MISSING_PS4_ICON = '/img/missing-ps4-game.png';

    protected function __construct(
        private string $iconUrl,
        private string $platform,
        private HistoryIconType $iconType,
    ) {
    }

    public function getIconPath(): string
    {
        if ($this->iconUrl === '' || $this->iconUrl === '.png') {
            return Platform::usesPlayStation5Assets($this->platform)
                ? self::MISSING_PS5_ICON
                : self::MISSING_PS4_ICON;
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
}
