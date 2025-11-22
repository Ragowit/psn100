<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyRarityFormatter.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyRarity.php';

final class TrophyRarityFormatterTest extends TestCase
{
    public function testFormatReturnsUnobtainableWhenStatusIsOne(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->formatMeta(42, 1);

        $this->assertSame('42.00', $rarity->getPercentage());
        $this->assertSame('Unobtainable', $rarity->getLabel());
        $this->assertSame(null, $rarity->getCssClass());
        $this->assertTrue($rarity->isUnobtainable());
    }

    public function testFormatClassifiesLegendaryAndTrimsString(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->formatMeta(' 0.015 ');

        $this->assertSame('0.02', $rarity->getPercentage());
        $this->assertSame('Legendary', $rarity->getLabel());
        $this->assertSame('trophy-legendary', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }

    public function testFormatClassifiesRareThreshold(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->formatMeta(0.5);

        $this->assertSame('0.50', $rarity->getPercentage());
        $this->assertSame('Rare', $rarity->getLabel());
        $this->assertSame('trophy-rare', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }

    public function testFormatDefaultsToCommonWhenValueIsNotNumeric(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->formatMeta('N/A');

        $this->assertSame('N/A', $rarity->getPercentage());
        $this->assertSame('Common', $rarity->getLabel());
        $this->assertSame('trophy-common', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }

    public function testFormatAddsTrailingZeroesForNumericStrings(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->formatMeta('1.1');

        $this->assertSame('1.10', $rarity->getPercentage());
        $this->assertSame('Rare', $rarity->getLabel());
        $this->assertSame('trophy-rare', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }

    public function testFormatInGameUsesInGameThresholds(): void
    {
        $formatter = new TrophyRarityFormatter();

        $legendary = $formatter->formatInGame(0.5);
        $epic = $formatter->formatInGame(2);
        $rare = $formatter->formatInGame(10);
        $uncommon = $formatter->formatInGame(40);
        $common = $formatter->formatInGame(80);

        $this->assertSame('Legendary', $legendary->getLabel());
        $this->assertSame('Epic', $epic->getLabel());
        $this->assertSame('Rare', $rare->getLabel());
        $this->assertSame('Uncommon', $uncommon->getLabel());
        $this->assertSame('Common', $common->getLabel());
    }
}
