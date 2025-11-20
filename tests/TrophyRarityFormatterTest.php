<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyRarityFormatter.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyRarity.php';

final class TrophyRarityFormatterTest extends TestCase
{
    public function testFormatReturnsUnobtainableWhenStatusIsOne(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->format(42, 1);

        $this->assertSame('42.00', $rarity->getPercentage());
        $this->assertSame('Unobtainable', $rarity->getLabel());
        $this->assertSame(null, $rarity->getCssClass());
        $this->assertTrue($rarity->isUnobtainable());
    }

    public function testFormatClassifiesLegendaryAndTrimsString(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->format(' 0.015 ');

        $this->assertSame('0.02', $rarity->getPercentage());
        $this->assertSame('Legendary', $rarity->getLabel());
        $this->assertSame('trophy-legendary', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }

    public function testFormatClassifiesRareThreshold(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->format(0.5);

        $this->assertSame('0.50', $rarity->getPercentage());
        $this->assertSame('Rare', $rarity->getLabel());
        $this->assertSame('trophy-rare', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }

    public function testFormatDefaultsToCommonWhenValueIsNotNumeric(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->format('N/A');

        $this->assertSame('N/A', $rarity->getPercentage());
        $this->assertSame('Common', $rarity->getLabel());
        $this->assertSame('trophy-common', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }

    public function testFormatAddsTrailingZeroesForNumericStrings(): void
    {
        $formatter = new TrophyRarityFormatter();

        $rarity = $formatter->format('1.1');

        $this->assertSame('1.10', $rarity->getPercentage());
        $this->assertSame('Rare', $rarity->getLabel());
        $this->assertSame('trophy-rare', $rarity->getCssClass());
        $this->assertFalse($rarity->isUnobtainable());
    }
}
