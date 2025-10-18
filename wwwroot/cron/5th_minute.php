<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobBootstrapper.php';
require_once dirname(__DIR__) . '/classes/Cron/PlayerRankingCronJob.php';

$bootstrapper = CronJobBootstrapper::create(dirname(__DIR__));
$bootstrapper->bootstrap();

$bootstrapper->run(static function (\PDO $database): CronJobInterface {
    $playerRankingUpdater = new PlayerRankingUpdater($database);

    return new PlayerRankingCronJob($playerRankingUpdater);
});
