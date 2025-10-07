<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobRunner.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobCliArguments.php';

$cronJobRunner = CronJobRunner::create();
$cronJobRunner->configureEnvironment();

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/TrophyCalculator.php';
require_once dirname(__DIR__) . '/classes/Cron/ThirtyMinuteCronJob.php';

$cliArguments = CronJobCliArguments::fromArgv($_SERVER['argv'] ?? []);
$workerId = $cliArguments->getWorkerId();

$trophyCalculator = new TrophyCalculator($database);
$logger = new Psn100Logger($database);

$cronJob = new ThirtyMinuteCronJob($database, $trophyCalculator, $logger, $workerId);
$cronJobRunner->run($cronJob);
