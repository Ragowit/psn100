<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobEntryPoint.php';
require_once dirname(__DIR__) . '/classes/Cron/WeeklyCronJob.php';

$entryPoint = CronJobEntryPoint::create(dirname(__DIR__));
$entryPoint->runJob(WeeklyCronJob::class);
