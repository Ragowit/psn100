<?php

declare(strict_types=1);

ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/classes/Cron/WeeklyCronJob.php';

$weeklyCronJob = new WeeklyCronJob($database);
$weeklyCronJob->run();
