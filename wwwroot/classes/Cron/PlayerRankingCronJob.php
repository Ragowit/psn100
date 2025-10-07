<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';
require_once __DIR__ . '/PlayerRankingUpdater.php';

final class PlayerRankingCronJob implements CronJobInterface
{
    private PlayerRankingUpdater $playerRankingUpdater;

    public function __construct(PlayerRankingUpdater $playerRankingUpdater)
    {
        $this->playerRankingUpdater = $playerRankingUpdater;
    }

    public function run(): void
    {
        $this->playerRankingUpdater->recalculate();
    }
}
