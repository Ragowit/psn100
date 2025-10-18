<?php

declare(strict_types=1);

require_once __DIR__ . '/NavigationState.php';
require_once __DIR__ . '/NavigationMenu.php';

final class NavigationBarRenderer
{
    private NavigationState $state;

    private NavigationMenu $menu;

    private function __construct(NavigationState $state, NavigationMenu $menu)
    {
        $this->state = $state;
        $this->menu = $menu;
    }

    public static function create(NavigationState $state, ?NavigationMenu $menu = null): self
    {
        return new self($state, $menu ?? NavigationMenu::createDefault($state));
    }

    public function render(): string
    {
        $sort = $this->state->getSort();
        $player = $this->state->getPlayer();
        $filter = $this->state->getFilter();
        $search = $this->state->getSearch();

        $itemsHtml = $this->renderNavigationItems();

        return <<<HTML
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-2">
    <div class="container">
        <a class="navbar-brand" href="/">
            <img src="/img/logo-via-logohub.png" alt="PSN 100%" height="24">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
            <form action="/game" class="d-flex" role="search">
                <input type="hidden" name="sort" value="{$sort}">
                <input type="hidden" name="player" value="{$player}">
                <input type="hidden" name="filter" value="{$filter}">
                <input class="form-control me-2" name="search" type="search" placeholder="Search game..." aria-label="Search" value="{$search}">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>

            <ul class="navbar-nav ms-auto mb-2 mb-md-0">
{$itemsHtml}
            </ul>
        </div>
    </div>
</nav>
HTML;
    }

    private function renderNavigationItems(): string
    {
        $items = [];

        foreach ($this->menu->getItems() as $item) {
            $linkClass = htmlspecialchars($item->getLinkCssClass(), ENT_QUOTES, 'UTF-8');
            $href = htmlspecialchars($item->getHref(), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($item->getLabel(), ENT_QUOTES, 'UTF-8');
            $ariaAttribute = $this->renderAriaAttribute($item->getAriaCurrentValue());

            $items[] = sprintf(
                '                <li class="nav-item"><a class="%s" href="%s"%s>%s</a></li>',
                $linkClass,
                $href,
                $ariaAttribute,
                $label
            );
        }

        return implode(PHP_EOL, $items);
    }

    private function renderAriaAttribute(?string $ariaCurrent): string
    {
        if ($ariaCurrent === null) {
            return '';
        }

        $value = htmlspecialchars($ariaCurrent, ENT_QUOTES, 'UTF-8');

        return ' aria-current="' . $value . '"';
    }
}
