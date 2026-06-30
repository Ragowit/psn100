<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Routing/TrophyRouteHandler.php';

final class TrophyRouteHandlerTest extends TestCase
{
    private TrophyRepository $trophyRepository;

    private TrophyRouteHandler $handler;

    protected function setUp(): void
    {
        $this->trophyRepository = new class extends TrophyRepository {
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

        $this->handler = new TrophyRouteHandler($this->trophyRepository);
    }

    public function testHandleIncludesValidPlayerSegment(): void
    {
        $this->trophyRepository->idsBySegment['42-example-trophy'] = 42;

        $result = $this->handler->handle(['42-example-trophy', 'player42']);

        $this->assertTrue($result->shouldInclude());
        $this->assertSame('trophy.php', $result->getInclude());
        $this->assertSame(
            [
                'trophyId' => 42,
                'player' => 'player42',
            ],
            $result->getVariables()
        );
    }

    public function testHandleIgnoresInvalidPlayerSegment(): void
    {
        $this->trophyRepository->idsBySegment['42-example-trophy'] = 42;

        $result = $this->handler->handle(['42-example-trophy', '<script>alert(1)</script>']);

        $this->assertTrue($result->shouldInclude());
        $this->assertSame(
            [
                'trophyId' => 42,
                'player' => null,
            ],
            $result->getVariables()
        );
    }

    public function testHandleRedirectsWhenTrophySegmentIsUnknown(): void
    {
        $result = $this->handler->handle(['missing-trophy', 'player42']);

        $this->assertTrue($result->shouldRedirect());
        $this->assertSame('/trophy/', $result->getRedirect());
    }
}
