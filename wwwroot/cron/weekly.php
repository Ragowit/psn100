<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobRunner.php';
require_once dirname(__DIR__) . '/classes/Cron/WeeklyCronJob.php';

$cronJobRunner = CronJobRunner::create();
$weeklyCronJob = new WeeklyCronJob($database);
$cronJobRunner->run($weeklyCronJob);
