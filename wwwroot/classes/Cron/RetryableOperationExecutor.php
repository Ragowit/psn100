<?php

declare(strict_types=1);

/**
 * Executes operations that might need to be retried due to transient failures.
 */
class RetryableOperationExecutor
{
    private int $retryDelaySeconds;

    /**
     * @var class-string<Throwable>[]
     */
    private array $retryableExceptions;

    /**
     * @var callable
     */
    private $sleeper;

    /**
     * @param class-string<Throwable>[] $retryableExceptions
     * @param callable(int):void|null $sleeper
     */
    public function __construct(
        int $retryDelaySeconds = 3,
        array $retryableExceptions = [Throwable::class],
        ?callable $sleeper = null
    ) {
        $this->retryDelaySeconds = $retryDelaySeconds;
        $this->retryableExceptions = $retryableExceptions;
        $this->sleeper = $sleeper ?? static function (int $seconds): void {
            sleep($seconds);
        };
    }

    public function execute(callable $operation): void
    {
        while (true) {
            try {
                $operation();

                return;
            } catch (Throwable $exception) {
                if (!$this->shouldRetryFor($exception)) {
                    throw $exception;
                }

                ($this->sleeper)($this->retryDelaySeconds);
            }
        }
    }

    private function shouldRetryFor(Throwable $throwable): bool
    {
        foreach ($this->retryableExceptions as $retryableException) {
            if ($throwable instanceof $retryableException) {
                return true;
            }
        }

        return false;
    }
}
