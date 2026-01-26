<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerNavigation.php';

final class PlayerNavigationTest extends TestCase
{
    public function testGetLinksReturnsExpectedLinksWithUrlEncoding(): void
    {
        $navigation = PlayerNavigation::forSection('Test User');

        $links = $navigation->getLinks();

        $this->assertCount(6, $links);

        $expected = [
            ['label' => 'Games', 'url' => '/player/Test%20User'],
            ['label' => 'Timeline', 'url' => '/player/Test%20User/timeline'],
            ['label' => 'Log', 'url' => '/player/Test%20User/log'],
            ['label' => 'Trophy Advisor', 'url' => '/player/Test%20User/advisor'],
            ['label' => 'Game Advisor', 'url' => '/game?sort=completion&filter=true&player=Test%20User'],
            ['label' => 'Random Games', 'url' => '/player/Test%20User/random'],
        ];

        foreach ($links as $index => $link) {
            $this->assertSame($expected[$index]['label'], $link->getLabel(), 'Unexpected label at index ' . $index);
            $this->assertSame($expected[$index]['url'], $link->getUrl(), 'Unexpected URL at index ' . $index);
            $this->assertFalse($link->isActive(), 'No section should be active.');
            $this->assertSame('btn btn-outline-primary', $link->getButtonCssClass(), 'Inactive link should use outline style.');
            $this->assertSame(null, $link->getAriaCurrent(), 'Inactive link should not have aria-current attribute.');
        }
    }

    public function testActiveSectionProducesActiveLinkStylingAndAriaAttributes(): void
    {
        $navigation = PlayerNavigation::forSection('player+one', PlayerNavigationSection::GAME_ADVISOR);

        $links = $navigation->getLinks();

        $this->assertCount(6, $links);

        foreach ($links as $link) {
            if ($link->getLabel() === 'Game Advisor') {
                $this->assertTrue($link->isActive());
                $this->assertSame('btn btn-primary active', $link->getButtonCssClass());
                $this->assertSame('page', $link->getAriaCurrent());
                $this->assertSame('/game?sort=completion&filter=true&player=player%2Bone', $link->getUrl());
                continue;
            }

            $this->assertFalse($link->isActive(), 'Only Game Advisor link should be active.');
            $this->assertSame('btn btn-outline-primary', $link->getButtonCssClass());
            $this->assertSame(null, $link->getAriaCurrent());
        }
    }
}
