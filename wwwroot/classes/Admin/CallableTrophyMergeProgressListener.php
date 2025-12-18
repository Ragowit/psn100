<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyMergeProgressListener.php';

final class CallableTrophyMergeProgressListener implements TrophyMergeProgressListener
{
    /**
     * @var \Closure(int, string):void
     */
    private readonly \Closure $callback;

    /**
     * @param callable(int, string):void $callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = \Closure::fromCallable($callback);
    }

    #[\Override]
    public function onProgress(int $percent, string $message): void
    {
        ($this->callback)($percent, $message);
    }
}
