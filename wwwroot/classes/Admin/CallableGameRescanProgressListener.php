<?php

declare(strict_types=1);

require_once __DIR__ . '/GameRescanProgressListener.php';

final class CallableGameRescanProgressListener implements GameRescanProgressListener
{
    /**
     * @param Closure(int, string):void $callback
     */
    public function __construct(private readonly \Closure $callback)
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
