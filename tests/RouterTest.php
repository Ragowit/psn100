<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Router.php';

final class RouterTest extends TestCase
{
    private Router $router;

    private GameRepository $gameRepository;

    private TrophyRepository $trophyRepository;

    private PlayerRepository $playerRepository;

    protected function setUp(): void
    {
        $this->gameRepository = new class extends GameRepository {
            /** @var list<string|null> */
            public array $receivedSegments = [];

            /** @var array<string, int> */
            public array $idsBySegment = [];

            public function __construct()
            {
                // The parent constructor requires a PDO instance, which is not needed for the tests.
            }

            public function findIdFromSegment(?string $segment): ?int
            {
                $this->receivedSegments[] = $segment;

                if ($segment === null) {
                    return null;
                }

                return $this->idsBySegment[$segment] ?? null;
            }
        };

        $this->trophyRepository = new class extends TrophyRepository {
            /** @var list<string|null> */
            public array $receivedSegments = [];

            /** @var array<string, int> */
            public array $idsBySegment = [];

            public function __construct()
            {
                // The parent constructor requires a PDO instance, which is not needed for the tests.
            }

            public function findIdFromSegment(?string $segment): ?int
            {
                $this->receivedSegments[] = $segment;

                if ($segment === null) {
                    return null;
                }

                return $this->idsBySegment[$segment] ?? null;
            }
        };

        $this->playerRepository = new class extends PlayerRepository {
            public ?string $lastRequestedOnlineId = null;

            public ?int $lastRequestedAccountId = null;

            /** @var array<string, int> */
            public array $accountIdsByOnlineId = [];

            /** @var array<int, array> */
            public array $playersByAccountId = [];

            public function __construct()
            {
                // The parent constructor requires a PDO instance, which is not needed for the tests.
            }

            public function findAccountIdByOnlineId(string $onlineId): ?int
            {
                $this->lastRequestedOnlineId = $onlineId;

                return $this->accountIdsByOnlineId[$onlineId] ?? null;
            }

            public function fetchPlayerByAccountId(int $accountId): ?array
            {
                $this->lastRequestedAccountId = $accountId;

                return $this->playersByAccountId[$accountId] ?? null;
            }
        };

        $this->router = new Router($this->gameRepository, $this->trophyRepository, $this->playerRepository);
    }

    public function testDispatchReturnsHomePageForEmptyPath(): void
    {
        $result = $this->router->dispatch('/');

        $this->assertTrue($result->shouldInclude());
        $this->assertSame('home.php', $result->getInclude());
        $this->assertFalse($result->shouldRedirect());
        $this->assertFalse($result->isNotFound());
    }

    public function testDispatchRoutesToSimplePageWithoutExtraSegments(): void
    {
        $result = $this->router->dispatch('/about');

        $this->assertTrue($result->shouldInclude());
        $this->assertSame('about.php', $result->getInclude());
    }

    public function testDispatchRedirectsSimplePageWhenExtraSegmentsPresent(): void
    {
        $result = $this->router->dispatch('/about/more');

        $this->assertTrue($result->shouldRedirect());
        $this->assertSame('/about/', $result->getRedirect());
        $this->assertFalse($result->shouldInclude());
    }

    public function testDispatchReturnsNotFoundForUnknownRoute(): void
    {
        $result = $this->router->dispatch('/unknown');

        $this->assertTrue($result->isNotFound());
        $this->assertSame(404, $result->getStatusCode());
        $this->assertFalse($result->shouldInclude());
        $this->assertFalse($result->shouldRedirect());
    }

    public function testDispatchProvidesGameVariablesWhenRouteMatches(): void
    {
        $this->gameRepository->idsBySegment['123-example'] = 123;

        $result = $this->router->dispatch('/game/123-example/player42');

        $this->assertSame(['123-example'], array_filter($this->gameRepository->receivedSegments));
        $this->assertTrue($result->shouldInclude());
        $this->assertSame('game.php', $result->getInclude());
        $this->assertSame(
            [
                'gameId' => 123,
                'player' => 'player42',
            ],
            $result->getVariables()
        );
    }

    public function testDispatchProvidesTrophyVariablesWhenRouteMatches(): void
    {
        $this->trophyRepository->idsBySegment['55-trophy'] = 55;

        $result = $this->router->dispatch('/trophy/55-trophy/some-player');

        $this->assertSame(['55-trophy'], array_filter($this->trophyRepository->receivedSegments));
        $this->assertTrue($result->shouldInclude());
        $this->assertSame('trophy.php', $result->getInclude());
        $this->assertSame(
            [
                'trophyId' => 55,
                'player' => 'some-player',
            ],
            $result->getVariables()
        );
    }

    public function testDispatchProvidesPlayerVariablesForKnownPlayer(): void
    {
        $this->playerRepository->accountIdsByOnlineId['Some User'] = 99;
        $this->playerRepository->playersByAccountId[99] = ['account_id' => 99, 'name' => 'Some User'];

        $result = $this->router->dispatch('/player/Some%20User/log');

        $this->assertSame('Some User', $this->playerRepository->lastRequestedOnlineId);
        $this->assertSame(99, $this->playerRepository->lastRequestedAccountId);
        $this->assertTrue($result->shouldInclude());
        $this->assertSame('player_log.php', $result->getInclude());
        $this->assertSame(
            [
                'accountId' => 99,
                'player' => ['account_id' => 99, 'name' => 'Some User'],
                'onlineId' => 'Some User',
            ],
            $result->getVariables()
        );
    }

    public function testDispatchRedirectsUnknownPlayerToListing(): void
    {
        $result = $this->router->dispatch('/player/Unknown');

        $this->assertTrue($result->shouldRedirect());
        $this->assertSame('/player/', $result->getRedirect());
    }
}
