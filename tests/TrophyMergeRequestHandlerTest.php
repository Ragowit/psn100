<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyMergeRequestHandler.php';

final class TrophyMergeRequestHandlerTest extends TestCase
{
    public function testHandleReturnsEmptyStringForEmptyPostData(): void
    {
        $handler = new TrophyMergeRequestHandler(new ThrowingTrophyMergeService(new RuntimeException('unused')));

        $this->assertSame('', $handler->handle([]));
    }

    public function testHandleEscapesInvalidArgumentExceptionMessage(): void
    {
        $handler = new TrophyMergeRequestHandler(
            new ThrowingTrophyMergeService(
                new InvalidArgumentException('<script>alert(1)</script>')
            )
        );

        $message = $handler->handle([
            'trophyparent' => '1',
            'trophychild' => '2',
        ]);

        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', $message);
    }

    public function testHandleEscapesRuntimeExceptionMessage(): void
    {
        $handler = new TrophyMergeRequestHandler(
            new ThrowingTrophyMergeService(
                new RuntimeException('Unable to locate child & trophy titles.')
            )
        );

        $message = $handler->handle([
            'clone' => '42',
        ]);

        $this->assertSame('Unable to locate child &amp; trophy titles.', $message);
    }

    public function testHandleReturnsGenericMessageForUnexpectedErrors(): void
    {
        $handler = new TrophyMergeRequestHandler(
            new ThrowingTrophyMergeService(
                new Exception('<img src=x onerror=alert(1)>')
            )
        );

        $message = $handler->handle([
            'parent' => '10',
            'child' => '20',
        ]);

        $this->assertSame('An unexpected error occurred while processing the request.', $message);
    }

    public function testHandleEscapesValidationErrorsBeforeCallingService(): void
    {
        $handler = new TrophyMergeRequestHandler(
            new ThrowingTrophyMergeService(new RuntimeException('Service should not be called.'))
        );

        $message = $handler->handle([
            'trophyparent' => '1',
            'trophychild' => 'abc',
        ]);

        $this->assertSame('Child trophy ids must be numeric.', $message);
    }
}

final class ThrowingTrophyMergeService extends TrophyMergeService
{
    public function __construct(private readonly Throwable $throwable)
    {
        parent::__construct(new PDO('sqlite::memory:'));
    }

    public function mergeSpecificTrophies(int $parentTrophyId, array $childTrophyIds): string
    {
        throw $this->throwable;
    }

    public function cloneGame(int $childGameId): string
    {
        throw $this->throwable;
    }

    public function mergeGames(
        int $childGameId,
        int $parentGameId,
        TrophyMergeMethod|string $method,
        ?TrophyMergeProgressListener $progressListener = null
    ): string {
        throw $this->throwable;
    }
}
