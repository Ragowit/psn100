<?php

declare(strict_types=1);

final class PlayerNavigation
{
    public const SECTION_GAMES = 'games';
    public const SECTION_LOG = 'log';
    public const SECTION_TROPHY_ADVISOR = 'trophy-advisor';
    public const SECTION_GAME_ADVISOR = 'game-advisor';
    public const SECTION_RANDOM = 'random';

    private string $onlineId;

    private ?string $activeSection;

    private function __construct(string $onlineId, ?string $activeSection)
    {
        $this->onlineId = $onlineId;
        $this->activeSection = $activeSection;
    }

    public static function forSection(string $onlineId, ?string $activeSection = null): self
    {
        return new self($onlineId, $activeSection);
    }

    /**
     * @return PlayerNavigationLink[]
     */
    public function getLinks(): array
    {
        $encodedOnlineId = rawurlencode($this->onlineId);

        return [
            new PlayerNavigationLink(
                'Games',
                '/player/' . $encodedOnlineId,
                $this->isActive(self::SECTION_GAMES)
            ),
            new PlayerNavigationLink(
                'Log',
                '/player/' . $encodedOnlineId . '/log',
                $this->isActive(self::SECTION_LOG)
            ),
            new PlayerNavigationLink(
                'Trophy Advisor',
                '/player/' . $encodedOnlineId . '/advisor',
                $this->isActive(self::SECTION_TROPHY_ADVISOR)
            ),
            new PlayerNavigationLink(
                'Game Advisor',
                '/game?sort=completion&filter=true&player=' . $encodedOnlineId,
                $this->isActive(self::SECTION_GAME_ADVISOR)
            ),
            new PlayerNavigationLink(
                'Random Games',
                '/player/' . $encodedOnlineId . '/random',
                $this->isActive(self::SECTION_RANDOM)
            ),
        ];
    }

    private function isActive(string $section): bool
    {
        return $this->activeSection === $section;
    }
}

final class PlayerNavigationLink
{
    private string $label;

    private string $url;

    private bool $active;

    public function __construct(string $label, string $url, bool $active)
    {
        $this->label = $label;
        $this->url = $url;
        $this->active = $active;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getButtonCssClass(): string
    {
        return $this->active
            ? 'btn btn-primary active'
            : 'btn btn-outline-primary';
    }

    public function getAriaCurrent(): ?string
    {
        return $this->active ? 'page' : null;
    }
}
