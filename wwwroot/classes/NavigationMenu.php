<?php

declare(strict_types=1);

require_once __DIR__ . '/NavigationSection.php';
require_once __DIR__ . '/NavigationState.php';

final class NavigationMenu
{
    /**
     * @param NavigationMenuItem[] $items
     */
    private function __construct(private readonly array $items)
    {
    }

    public static function createDefault(NavigationState $state): self
    {
        $items = [
            new NavigationMenuItem('Home', '/', $state->isSectionActive(NavigationSection::Home)),
            new NavigationMenuItem('Leaderboards', '/leaderboard/trophy', $state->isSectionActive(NavigationSection::Leaderboard)),
            new NavigationMenuItem('Games', '/game', $state->isSectionActive(NavigationSection::Game)),
            new NavigationMenuItem('Trophies', '/trophy', $state->isSectionActive(NavigationSection::Trophy)),
            new NavigationMenuItem('Avatars', '/avatar', $state->isSectionActive(NavigationSection::Avatar)),
            new NavigationMenuItem('About', '/about', $state->isSectionActive(NavigationSection::About)),
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

final readonly class NavigationMenuItem
{
    public function __construct(private string $label, private string $href, private bool $active)
    {
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
