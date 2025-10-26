<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyRarity.php';

final class TrophyRarityTest extends TestCase
{
    public function testRenderSpanIncludesPercentageAndCssClass(): void
    {
        $rarity = new TrophyRarity('12.5', 'Common', 'trophy-common', false);

        $html = $rarity->renderSpan();

        $this->assertSame('<span class="trophy-common">12.5%<br>Common</span>', $html);
    }

    public function testRenderSpanSkipsPercentageWhenUnobtainable(): void
    {
        $rarity = new TrophyRarity('0.1', 'Unobtainable', null, true);

        $html = $rarity->renderSpan();

        $this->assertSame('<span>Unobtainable</span>', $html);
    }

    public function testRenderSpanIncludesPercentageWhenRequestedForUnobtainable(): void
    {
        $rarity = new TrophyRarity('0.1', 'Unobtainable', 'trophy-unobtainable', true);

        $html = $rarity->renderSpan(' / ', true);

        $this->assertSame('<span class="trophy-unobtainable">0.1% / Unobtainable</span>', $html);
    }
}
