<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/NavigationSection.php';
require_once __DIR__ . '/../wwwroot/classes/NavigationSectionState.php';
require_once __DIR__ . '/TestCase.php';

final class NavigationSectionStateTest extends TestCase
{
    public function testActiveSectionReportsCssClassAndState(): void
    {
        $state = new NavigationSectionState(NavigationSection::Leaderboard, true);

        $this->assertSame(NavigationSection::Leaderboard, $state->getSection());
        $this->assertTrue($state->isActive());
        $this->assertSame(' active', $state->getCssClass());
    }

    public function testInactiveSectionHasEmptyCssClass(): void
    {
        $state = new NavigationSectionState(NavigationSection::Home, false);

        $this->assertFalse($state->isActive());
        $this->assertSame('', $state->getCssClass());
    }
}
