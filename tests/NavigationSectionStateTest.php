<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/NavigationSectionState.php';
require_once __DIR__ . '/TestCase.php';

final class NavigationSectionStateTest extends TestCase
{
    public function testActiveSectionReportsCssClassAndState(): void
    {
        $state = new NavigationSectionState('leaderboard', true);

        $this->assertSame('leaderboard', $state->getSection());
        $this->assertTrue($state->isActive());
        $this->assertSame(' active', $state->getCssClass());
    }

    public function testInactiveSectionHasEmptyCssClass(): void
    {
        $state = new NavigationSectionState('home', false);

        $this->assertFalse($state->isActive());
        $this->assertSame('', $state->getCssClass());
    }
}
