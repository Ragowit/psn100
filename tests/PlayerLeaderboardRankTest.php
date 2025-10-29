<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerLeaderboardRank.php';

final class PlayerLeaderboardRankTest extends TestCase
{
    public function testWorldRankProvidesUrlAndChangeDetails(): void
    {
        $rank = PlayerLeaderboardRank::createWorldRank('/leaderboard', 'player-one', 120, 150, true);

        $this->assertSame('World Rank', $rank->getLabel());
        $this->assertTrue($rank->isAvailable());
        $this->assertSame(120, $rank->getRank());
        $this->assertSame('/leaderboard?page=3&player=player-one#player-one', $rank->getUrl());

        $change = $rank->getChange();
        if ($change === null) {
            $this->fail('Expected a rank change for an available rank.');
        }

        $this->assertFalse($change->isNew());
        $this->assertTrue($change->shouldDisplay());
        $this->assertSame('(+30)', $change->getDisplayText());
        $this->assertSame('#0bd413', $change->getColor());
    }

    public function testWorldRankIsUnavailableWhenInactiveOrMissingRank(): void
    {
        $inactiveRank = PlayerLeaderboardRank::createWorldRank('/leaderboard', 'player-two', 10, 12, false);

        $this->assertFalse($inactiveRank->isAvailable());
        $this->assertSame(null, $inactiveRank->getRank());
        $this->assertSame(null, $inactiveRank->getUrl());
        $this->assertSame(null, $inactiveRank->getChange());

        $missingRank = PlayerLeaderboardRank::createWorldRank('/leaderboard', 'player-three', 0, 0, true);

        $this->assertFalse($missingRank->isAvailable());
        $this->assertSame(null, $missingRank->getRank());
        $this->assertSame(null, $missingRank->getUrl());
        $this->assertSame(null, $missingRank->getChange());
    }

    public function testCountryRankIncludesCountryInUrlAndTreatsFirstPageAsOne(): void
    {
        $rank = PlayerLeaderboardRank::createCountryRank('/leaderboard', 'player-four', 'DE', 50, 0, true);

        $this->assertTrue($rank->isAvailable());
        $this->assertSame(50, $rank->getRank());
        $this->assertSame('/leaderboard?country=DE&page=1&player=player-four#player-four', $rank->getUrl());

        $change = $rank->getChange();
        if ($change === null) {
            $this->fail('Expected a rank change for an available rank.');
        }

        $this->assertTrue($change->isNew());
        $this->assertTrue($change->shouldDisplay());
        $this->assertSame('(New!)', $change->getDisplayText());
        $this->assertSame(null, $change->getColor());
    }
}
