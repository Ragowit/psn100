<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerLeaderboardRankChange.php';

final class PlayerLeaderboardRankChangeTest extends TestCase
{
    public function testFromRanksReturnsNewRankWhenPreviousRankIsZero(): void
    {
        $rankChange = PlayerLeaderboardRankChange::fromRanks(150, 0);

        $this->assertTrue($rankChange->isNew());
        $this->assertTrue($rankChange->shouldDisplay());
        $this->assertSame('(New!)', $rankChange->getDisplayText());
        $this->assertSame(null, $rankChange->getColor());
    }

    public function testFromRanksReturnsNewRankWhenPreviousRankIsSentinel(): void
    {
        $rankChange = PlayerLeaderboardRankChange::fromRanks(150, 16777215);

        $this->assertTrue($rankChange->isNew());
        $this->assertTrue($rankChange->shouldDisplay());
        $this->assertSame('(New!)', $rankChange->getDisplayText());
        $this->assertSame(null, $rankChange->getColor());
    }

    public function testPositiveRankImprovement(): void
    {
        $rankChange = PlayerLeaderboardRankChange::fromRanks(90, 120);

        $this->assertFalse($rankChange->isNew());
        $this->assertTrue($rankChange->shouldDisplay());
        $this->assertSame('(+30)', $rankChange->getDisplayText());
        $this->assertSame('#0bd413', $rankChange->getColor());
    }

    public function testNegativeRankChange(): void
    {
        $rankChange = PlayerLeaderboardRankChange::fromRanks(150, 120);

        $this->assertFalse($rankChange->isNew());
        $this->assertTrue($rankChange->shouldDisplay());
        $this->assertSame('(-30)', $rankChange->getDisplayText());
        $this->assertSame('#d40b0b', $rankChange->getColor());
    }

    public function testNoRankChange(): void
    {
        $rankChange = PlayerLeaderboardRankChange::fromRanks(120, 120);

        $this->assertFalse($rankChange->isNew());
        $this->assertTrue($rankChange->shouldDisplay());
        $this->assertSame('(=)', $rankChange->getDisplayText());
        $this->assertSame('#0070d1', $rankChange->getColor());
    }
}
