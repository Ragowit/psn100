<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronJobBootstrapper.php';
require_once dirname(__DIR__) . '/classes/Cron/CronJobCliArguments.php';
require_once dirname(__DIR__) . '/classes/TrophyCalculator.php';
require_once dirname(__DIR__) . '/classes/Cron/ThirtyMinuteCronJob.php';

$bootstrapper = CronJobBootstrapper::create(dirname(__DIR__));
$bootstrapper->bootstrap(true);

$cliArguments = CronJobCliArguments::fromArgv($_SERVER['argv'] ?? []);
$workerId = $cliArguments->getWorkerId();

$bootstrapper->run(static function (\PDO $database) use ($workerId): CronJobInterface {
    $trophyCalculator = new TrophyCalculator($database);
    $logger = new Psn100Logger($database);

    return new ThirtyMinuteCronJob($database, $trophyCalculator, $logger, $workerId);
});
