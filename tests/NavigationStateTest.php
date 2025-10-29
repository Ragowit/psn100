<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/NavigationState.php';
require_once __DIR__ . '/TestCase.php';

final class NavigationStateTest extends TestCase
{
    public function testFromGlobalsWithLeaderboardUriSetsLeaderboardActive(): void
    {
        $server = ['REQUEST_URI' => '/leaderboard/top'];
        $queryParameters = ['sort' => 'points'];

        $navigationState = NavigationState::fromGlobals($server, $queryParameters);

        $this->assertSame(' active', $navigationState->getLeaderboardClass());
        $this->assertSame('', $navigationState->getHomeClass());
        $this->assertTrue($navigationState->isSectionActive('leaderboard'));
        $this->assertFalse($navigationState->isSectionActive('home'));
        $this->assertFalse($navigationState->isSectionActive('unknown'));
    }

    public function testUnknownUriDefaultsToHomeActive(): void
    {
        $server = ['REQUEST_URI' => '/something-else'];

        $navigationState = NavigationState::fromGlobals($server, []);

        $this->assertSame(' active', $navigationState->getHomeClass());
        $this->assertSame('', $navigationState->getLeaderboardClass());
        $this->assertTrue($navigationState->isSectionActive('home'));
        $this->assertFalse($navigationState->isSectionActive('leaderboard'));
    }

    public function testQueryParametersAreSanitized(): void
    {
        $server = ['REQUEST_URI' => '/'];
        $queryParameters = [
            'sort' => ['<sort>"test"', 'ignored'],
            'player' => '<b>Player</b>',
            'filter' => [],
            'search' => "<script>alert('x')</script>",
        ];

        $navigationState = NavigationState::fromGlobals($server, $queryParameters);

        $this->assertSame('&lt;sort&gt;&quot;test&quot;', $navigationState->getSort());
        $this->assertSame('&lt;b&gt;Player&lt;/b&gt;', $navigationState->getPlayer());
        $this->assertSame('', $navigationState->getFilter());
        $this->assertSame('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', $navigationState->getSearch());
    }
}
