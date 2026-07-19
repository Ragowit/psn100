<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerScanTrophyTitleLoopAction.php';

/**
 * Outcome of processing a player's trophy titles during a worker scan.
 */
final readonly class PlayerScanTrophyTitleLoopResult
{
    private function __construct(final public PlayerScanTrophyTitleLoopAction $action)
    {
    }

    #[\NoDiscard]
    public static function continueLoop(): self
    {
        return new self(PlayerScanTrophyTitleLoopAction::Continue);
    }

    #[\NoDiscard]
    public static function proceedToFinalize(): self
    {
        return new self(PlayerScanTrophyTitleLoopAction::Proceed);
    }

    public function shouldContinueLoop(): bool
    {
        return $this->action === PlayerScanTrophyTitleLoopAction::Continue;
    }
}
