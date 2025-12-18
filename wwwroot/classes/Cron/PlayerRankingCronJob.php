<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';
require_once __DIR__ . '/PlayerRankingUpdater.php';

final readonly class PlayerRankingCronJob implements CronJobInterface
{
    public function __construct(private PlayerRankingUpdater $playerRankingUpdater)
    {
    }

    #[\Override]
    public function run(): void
    {
        $this->playerRankingUpdater->recalculate();
    }
}
