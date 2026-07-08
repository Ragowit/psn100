<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobEntryPoint.php';
require_once dirname(__DIR__) . '/classes/Cron/PlayerRankingCronJob.php';
require_once dirname(__DIR__) . '/classes/Psn100Logger.php';

$entryPoint = CronJobEntryPoint::create(dirname(__DIR__));
$entryPoint->runWithFactory(static function (\PDO $database): CronJobInterface {
    $playerRankingUpdater = new PlayerRankingUpdater(
        $database,
        logger: new Psn100Logger($database),
    );

    return new PlayerRankingCronJob($playerRankingUpdater);
});
