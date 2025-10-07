<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobRunner.php';
require_once dirname(__DIR__) . '/classes/Cron/HourlyCronJob.php';

$cronJobRunner = CronJobRunner::create();
$hourlyCronJob = new HourlyCronJob($database);
$cronJobRunner->run($hourlyCronJob);
