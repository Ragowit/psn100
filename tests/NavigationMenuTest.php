<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/NavigationMenu.php';
require_once __DIR__ . '/TestCase.php';

final class NavigationMenuTest extends TestCase
{
    public function testCreateDefaultBuildsExpectedMenuItems(): void
    {
        $state = NavigationState::fromGlobals(['REQUEST_URI' => '/'], []);

        $menu = NavigationMenu::createDefault($state);
        $items = $menu->getItems();

        $this->assertCount(6, $items);

        $expectedItems = [
            ['label' => 'Home', 'href' => '/', 'active' => true],
            ['label' => 'Leaderboards', 'href' => '/leaderboard/trophy', 'active' => false],
            ['label' => 'Games', 'href' => '/game', 'active' => false],
            ['label' => 'Trophies', 'href' => '/trophy', 'active' => false],
            ['label' => 'Avatars', 'href' => '/avatar', 'active' => false],
            ['label' => 'About', 'href' => '/about', 'active' => false],
        ];

        foreach ($expectedItems as $index => $expectedItem) {
            $item = $items[$index];
            $this->assertSame($expectedItem['label'], $item->getLabel());
            $this->assertSame($expectedItem['href'], $item->getHref());
            $this->assertSame($expectedItem['active'], $item->isActive());
            $this->assertSame(
                $expectedItem['active'] ? 'nav-link active' : 'nav-link',
                $item->getLinkCssClass()
            );
            $this->assertSame(
                $expectedItem['active'] ? 'page' : null,
                $item->getAriaCurrentValue()
            );
        }
    }

    public function testActiveSectionReflectsNavigationState(): void
    {
        $state = NavigationState::fromGlobals(['REQUEST_URI' => '/trophy/latest'], []);

        $menu = NavigationMenu::createDefault($state);
        $items = $menu->getItems();

        $activeItems = [];
        foreach ($items as $item) {
            if ($item->isActive()) {
                $activeItems[] = $item;
            }
        }

        $this->assertCount(1, $activeItems);
        $this->assertSame('Trophies', $activeItems[0]->getLabel());
        $this->assertSame('/trophy', $activeItems[0]->getHref());
        $this->assertSame('nav-link active', $activeItems[0]->getLinkCssClass());
        $this->assertSame('page', $activeItems[0]->getAriaCurrentValue());

        foreach ($items as $item) {
            if ($item->getLabel() !== 'Trophies') {
                $this->assertFalse($item->isActive());
                $this->assertSame('nav-link', $item->getLinkCssClass());
                $this->assertSame(null, $item->getAriaCurrentValue());
            }
        }
    }
}
