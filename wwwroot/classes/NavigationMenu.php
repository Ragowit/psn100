<?php

declare(strict_types=1);

require_once __DIR__ . '/NavigationState.php';

final class NavigationMenu
{
    /**
     * @var NavigationMenuItem[]
     */
    private array $items;

    /**
     * @param NavigationMenuItem[] $items
     */
    private function __construct(array $items)
    {
        $this->items = $items;
    }

    public static function createDefault(NavigationState $state): self
    {
        $items = [
            new NavigationMenuItem('Home', '/', $state->isSectionActive('home')),
            new NavigationMenuItem('Leaderboards', '/leaderboard/trophy', $state->isSectionActive('leaderboard')),
            new NavigationMenuItem('Games', '/game', $state->isSectionActive('game')),
            new NavigationMenuItem('Trophies', '/trophy', $state->isSectionActive('trophy')),
            new NavigationMenuItem('Avatars', '/avatar', $state->isSectionActive('avatar')),
            new NavigationMenuItem('About', '/about', $state->isSectionActive('about')),
        ];

        return new self($items);
    }

    /**
     * @return NavigationMenuItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }
}

final class NavigationMenuItem
{
    private string $label;

    private string $href;

    private bool $active;

    public function __construct(string $label, string $href, bool $active)
    {
        $this->label = $label;
        $this->href = $href;
        $this->active = $active;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHref(): string
    {
        return $this->href;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getLinkCssClass(): string
    {
        return 'nav-link' . ($this->active ? ' active' : '');
    }

    public function getAriaCurrentValue(): ?string
    {
        return $this->active ? 'page' : null;
    }
}
