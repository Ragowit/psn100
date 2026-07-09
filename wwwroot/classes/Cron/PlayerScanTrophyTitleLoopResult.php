<?php

declare(strict_types=1);

/**
 * Outcome of processing a player's trophy titles during a worker scan.
 */
final class PlayerScanTrophyTitleLoopResult
{
    public const ACTION_CONTINUE = 'continue';
    public const ACTION_PROCEED = 'proceed';

    private function __construct(public readonly string $action)
    {
    }

    public static function continueLoop(): self
    {
        return new self(self::ACTION_CONTINUE);
    }

    public static function proceedToFinalize(): self
    {
        return new self(self::ACTION_PROCEED);
    }

    public function shouldContinueLoop(): bool
    {
        return $this->action === self::ACTION_CONTINUE;
    }
}
