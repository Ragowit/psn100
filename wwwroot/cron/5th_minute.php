<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobEntryPoint.php';
require_once dirname(__DIR__) . '/classes/Cron/PlayerRankingCronJob.php';

$entryPoint = CronJobEntryPoint::create(dirname(__DIR__));
$entryPoint->runWithFactory(static function (\PDO $database): CronJobInterface {
    $playerRankingUpdater = new PlayerRankingUpdater($database);

    return new PlayerRankingCronJob($playerRankingUpdater);
});
