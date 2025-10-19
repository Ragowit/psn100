<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobEntryPoint.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobCliArguments.php';
require_once dirname(__DIR__) . '/classes/TrophyCalculator.php';
require_once dirname(__DIR__) . '/classes/Cron/ThirtyMinuteCronJob.php';

$entryPoint = CronJobEntryPoint::create(dirname(__DIR__));

$cliArguments = CronJobCliArguments::fromArgv($_SERVER['argv'] ?? []);
$workerId = $cliArguments->getWorkerId();

$entryPoint->runWithFactory(static function (\PDO $database) use ($workerId): CronJobInterface {
    $trophyCalculator = new TrophyCalculator($database);
    $logger = new Psn100Logger($database);

    return new ThirtyMinuteCronJob($database, $trophyCalculator, $logger, $workerId);
}, true);
