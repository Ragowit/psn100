<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameStatusBadge.php';

final class GameStatusBadgeTest extends TestCase
{
    public function testConstructorAssignsLabelTooltipAndDefaultCssClass(): void
    {
        $badge = new GameStatusBadge('Delisted', 'The game is no longer available');

        $this->assertSame('Delisted', $badge->getLabel());
        $this->assertSame('The game is no longer available', $badge->getTooltip());
        $this->assertSame('badge rounded-pill text-bg-warning', $badge->getCssClass());
    }

    public function testConstructorAllowsOverridingCssClass(): void
    {
        $badge = new GameStatusBadge('Soon', 'The game releases soon', 'badge text-bg-info');

        $this->assertSame('badge text-bg-info', $badge->getCssClass());
    }
}
