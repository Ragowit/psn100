<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobRunner.php';

$cronJobRunner = CronJobRunner::create();
$cronJobRunner->configureEnvironment();

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/Cron/PlayerRankingCronJob.php';

$playerRankingUpdater = new PlayerRankingUpdater($database);
$playerRankingCronJob = new PlayerRankingCronJob($playerRankingUpdater);
$cronJobRunner->run($playerRankingCronJob);
