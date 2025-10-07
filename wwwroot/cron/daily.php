<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobRunner.php';
require_once dirname(__DIR__) . '/classes/Cron/DailyCronJob.php';

$cronJobRunner = CronJobRunner::create();
$dailyCronJob = new DailyCronJob($database);
$cronJobRunner->run($dailyCronJob);
