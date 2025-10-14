<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobRunner.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobApplication.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobCliArguments.php';

$application = CronJobApplication::create();
$application->configureEnvironment();

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/TrophyCalculator.php';
require_once dirname(__DIR__) . '/classes/Cron/ThirtyMinuteCronJob.php';

$application->run(static function () use ($database): CronJobInterface {
    $cliArguments = CronJobCliArguments::fromArgv($_SERVER['argv'] ?? []);
    $workerId = $cliArguments->getWorkerId();

    $trophyCalculator = new TrophyCalculator($database);
    $logger = new Psn100Logger($database);

    return new ThirtyMinuteCronJob($database, $trophyCalculator, $logger, $workerId);
});
