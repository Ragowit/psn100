<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerNavigationSection.php';
require_once __DIR__ . '/PlayerRouteView.php';
require_once __DIR__ . '/PlayerUrlBuilder.php';
require_once __DIR__ . '/GameListSort.php';

final readonly class PlayerNavigation
{
    private function __construct(
        final private string $onlineId,
        final private ?PlayerNavigationSection $activeSection,
    ) {
    }

    #[\NoDiscard]
    public static function forSection(string $onlineId, ?PlayerNavigationSection $activeSection = null): self
    {
        return new self($onlineId, $activeSection);
    }

    /**
     * @return PlayerNavigationLink[]
     */
    public function getLinks(): array
    {
        return [
            new PlayerNavigationLink(
                'Games',
                PlayerUrlBuilder::playerPath($this->onlineId),
                $this->isActive(PlayerNavigationSection::GAMES)
            ),
            new PlayerNavigationLink(
                'Timeline',
                PlayerUrlBuilder::playerViewPath($this->onlineId, PlayerRouteView::Timeline),
                $this->isActive(PlayerNavigationSection::TIMELINE)
            ),
            new PlayerNavigationLink(
                'Log',
                PlayerUrlBuilder::playerViewPath($this->onlineId, PlayerRouteView::Log),
                $this->isActive(PlayerNavigationSection::LOG)
            ),
            new PlayerNavigationLink(
                'Trophy Advisor',
                PlayerUrlBuilder::playerViewPath($this->onlineId, PlayerRouteView::Advisor),
                $this->isActive(PlayerNavigationSection::TROPHY_ADVISOR)
            ),
            new PlayerNavigationLink(
                'Game Advisor',
                PlayerUrlBuilder::gameAdvisorPath($this->onlineId),
                $this->isActive(PlayerNavigationSection::GAME_ADVISOR)
            ),
            new PlayerNavigationLink(
                'Random Games',
                PlayerUrlBuilder::playerViewPath($this->onlineId, PlayerRouteView::Random),
                $this->isActive(PlayerNavigationSection::RANDOM)
            ),
        ];
    }

    private function isActive(PlayerNavigationSection $section): bool
    {
        return $this->activeSection === $section;
    }
}

final readonly class PlayerNavigationLink
{
    public function __construct(
        final private string $label,
        final private string $url,
        final private bool $active,
    ) {
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
