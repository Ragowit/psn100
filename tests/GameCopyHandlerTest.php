<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameCopyService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameCopyHandler.php';

final class GameCopyHandlerTest extends TestCase
{
    public function testHandleReturnsEmptyStringWhenRequiredIdsAreMissing(): void
    {
        $handler = new GameCopyHandler(new ThrowingGameCopyService(new RuntimeException('unused')));

        $this->assertSame('', $handler->handle([]));
        $this->assertSame('', $handler->handle(['child' => '1']));
    }

    public function testHandleReturnsValidationMessageForNonNumericIds(): void
    {
        $handler = new GameCopyHandler(new ThrowingGameCopyService(new RuntimeException('unused')));

        $message = $handler->handle([
            'child' => 'abc',
            'parent' => '2',
        ]);

        $this->assertSame('Child and parent must be numeric IDs.', $message);
    }

    public function testHandleEscapesRuntimeExceptionMessage(): void
    {
        $handler = new GameCopyHandler(
            new ThrowingGameCopyService(
                new RuntimeException('<script>alert("copy")</script>')
            )
        );

        $message = $handler->handle([
            'child' => '1',
            'parent' => '2',
        ]);

        $this->assertSame('&lt;script&gt;alert(&quot;copy&quot;)&lt;/script&gt;', $message);
    }

    public function testHandleReturnsGenericMessageForUnexpectedErrors(): void
    {
        $handler = new GameCopyHandler(
            new ThrowingGameCopyService(
                new Exception('<img src=x onerror=alert(1)>')
            )
        );

        $message = $handler->handle([
            'child' => '1',
            'parent' => '2',
        ]);

        $this->assertSame('An unexpected error occurred while copying game data.', $message);
    }

    public function testHandleReturnsSuccessMessageWhenCopySucceeds(): void
    {
        $handler = new GameCopyHandler(new ThrowingGameCopyService(null));

        $message = $handler->handle([
            'child' => '1',
            'parent' => '2',
        ]);

        $this->assertSame('The group and trophy data have been copied.', $message);
    }
}

final class ThrowingGameCopyService extends GameCopyService
{
    public function __construct(private readonly ?Throwable $throwable)
    {
        parent::__construct(new PDO('sqlite::memory:'));
    }

    public function copyChildToParent(
        int $childId,
        int $parentId,
        bool $copyIconUrl = true,
        bool $copySetVersion = true
    ): void {
        if ($this->throwable !== null) {
            throw $this->throwable;
        }
    }
}
