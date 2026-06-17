<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Routing/GameRouteHandler.php';

final class GameRouteHandlerTest extends TestCase
{
    private GameRepository $gameRepository;

    private GameRouteHandler $handler;

    protected function setUp(): void
    {
        $this->gameRepository = new class extends GameRepository {
            /** @var array<string, int> */
            public array $idsBySegment = [];

            public function __construct()
            {
            }

            public function findIdFromSegment(?string $segment): ?int
            {
                if ($segment === null) {
                    return null;
                }

                return $this->idsBySegment[$segment] ?? null;
            }
        };

        $this->handler = new GameRouteHandler(
            $this->gameRepository,
            'game.php',
            '/game/',
            'games.php'
        );
    }

    public function testHandleIncludesValidPlayerSegment(): void
    {
        $this->gameRepository->idsBySegment['123-example'] = 123;

        $result = $this->handler->handle(['123-example', 'player42']);

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

    public function testHandleIgnoresInvalidPlayerSegment(): void
    {
        $this->gameRepository->idsBySegment['123-example'] = 123;

        $result = $this->handler->handle(['123-example', '<script>alert(1)</script>']);

        $this->assertTrue($result->shouldInclude());
        $this->assertSame(
            [
                'gameId' => 123,
                'player' => null,
            ],
            $result->getVariables()
        );
    }

    public function testHandleRedirectsWhenGameSegmentIsUnknown(): void
    {
        $result = $this->handler->handle(['missing-game', 'player42']);

        $this->assertTrue($result->shouldRedirect());
        $this->assertSame('/game/', $result->getRedirect());
    }
}
