<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Routing/LeaderboardRouteHandler.php';
require_once __DIR__ . '/TestCase.php';

final class LeaderboardRouteHandlerTest extends TestCase
{
    private LeaderboardRouteHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new LeaderboardRouteHandler();
        unset($_SERVER['QUERY_STRING']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['QUERY_STRING']);
    }

    public function testHandleIncludesDifficultyLeaderboard(): void
    {
        $result = $this->handler->handle(['difficulty']);

        $this->assertTrue($result->shouldInclude());
        $this->assertSame('leaderboard_difficulty.php', $result->getInclude());
    }

    public function testHandleRedirectsLegacyInGameRarityPath(): void
    {
        $result = $this->handler->handle(['in-game-rarity']);

        $this->assertTrue($result->shouldRedirect());
        $this->assertSame('/leaderboard/difficulty', $result->getRedirect());
    }

    public function testHandleRedirectsLegacyInGameRarityPathWithQueryString(): void
    {
        $_SERVER['QUERY_STRING'] = 'country=us&page=2';

        $result = $this->handler->handle(['in-game-rarity']);

        $this->assertTrue($result->shouldRedirect());
        $this->assertSame('/leaderboard/difficulty?country=us&page=2', $result->getRedirect());
    }

    public function testHandleRedirectsUnknownViewToTrophyLeaderboard(): void
    {
        $result = $this->handler->handle(['unknown']);

        $this->assertTrue($result->shouldRedirect());
        $this->assertSame('/leaderboard/trophy', $result->getRedirect());
    }
}
