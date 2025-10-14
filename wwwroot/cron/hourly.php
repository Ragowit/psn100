<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobRunner.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobApplication.php';

$application = CronJobApplication::create();
$application->configureEnvironment();

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/Cron/HourlyCronJob.php';

$application->run(static fn (): CronJobInterface => new HourlyCronJob($database));
