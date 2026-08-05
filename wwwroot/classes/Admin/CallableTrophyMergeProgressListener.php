<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyMergeProgressListener.php';

final readonly class CallableTrophyMergeProgressListener implements TrophyMergeProgressListener
{
    /**
     * @param Closure(int, string):void $callback
     */
    public function __construct(final private \Closure $callback)
    {
    }

    /**
     * @param callable(int, string):void $callback
     */
    #[\NoDiscard]
    public static function fromCallable(callable $callback): self
    {
        return new self($callback(...));
    }

    #[\Override]
    public function onProgress(int $percent, string $message): void
    {
        ($this->callback)($percent, $message);
    }
}
