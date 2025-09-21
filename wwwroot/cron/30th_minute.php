<?php

declare(strict_types=1);

parse_str(implode('&', array_slice($argv, 1)), $arguments);

//ini_set("max_execution_time", "0");
//ini_set("mysql.connect_timeout", "0");
//set_time_limit(0);
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/TrophyCalculator.php';
require_once dirname(__DIR__) . '/classes/Cron/ThirtyMinuteCronJob.php';

$trophyCalculator = new TrophyCalculator($database);
$logger = new Psn100Logger($database);

$workerId = isset($arguments['worker']) ? (int) $arguments['worker'] : 0;

$cronJob = new ThirtyMinuteCronJob($database, $trophyCalculator, $logger);
$cronJob->run($workerId);
