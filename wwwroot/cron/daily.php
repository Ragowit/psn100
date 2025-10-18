<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobBootstrapper.php';
require_once dirname(__DIR__) . '/classes/Cron/DailyCronJob.php';

$bootstrapper = CronJobBootstrapper::create(dirname(__DIR__));
$bootstrapper->bootstrap();

$bootstrapper->run(static fn (\PDO $database): CronJobInterface => new DailyCronJob($database));
