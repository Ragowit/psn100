<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStationTrophyLevelCalculator.php';

final class PlayStationTrophyLevelCalculatorTest extends TestCase
{
    public function testCalculateTrophyPointsUsesStandardPsnWeights(): void
    {
        $points = PlayStationTrophyLevelCalculator::calculateTrophyPoints(1, 2, 3, 1);

        $this->assertSame(645, $points);
    }

    public function testCalculateUsesFirstTierFormula(): void
    {
        $result = PlayStationTrophyLevelCalculator::calculate(120);

        $this->assertSame(3, $result['level']);
        $this->assertSame(0, $result['progress']);
    }

    public function testCalculateUsesExactIntegerProgressWithinTier(): void
    {
        $result = PlayStationTrophyLevelCalculator::calculate(59);

        $this->assertSame(1, $result['level']);
        $this->assertSame(98, $result['progress']);
    }

    public function testCalculateUsesSecondTierFormula(): void
    {
        $result = PlayStationTrophyLevelCalculator::calculate(6030);

        $this->assertSame(101, $result['level']);
        $this->assertSame(0, $result['progress']);
    }

    public function testCalculateUsesThirdTierFormula(): void
    {
        $result = PlayStationTrophyLevelCalculator::calculate(20000);

        $this->assertSame(211, $result['level']);
        $this->assertSame(24, $result['progress']);
    }

    public function testCalculationMethodsRequireTheirResultsToBeUsed(): void
    {
        $pointsAttribute = new ReflectionMethod(PlayStationTrophyLevelCalculator::class, 'calculateTrophyPoints')
            ->getAttributes(NoDiscard::class);
        $levelAttribute = new ReflectionMethod(PlayStationTrophyLevelCalculator::class, 'calculate')
            ->getAttributes(NoDiscard::class);

        $this->assertSame(1, count($pointsAttribute));
        $this->assertSame(1, count($levelAttribute));
    }
}
