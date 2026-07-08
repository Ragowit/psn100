<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/classes/Cron/ThirtyMinuteCronJobApplication.php';

$application = ThirtyMinuteCronJobApplication::fromGlobals(
    dirname(__DIR__),
    $_SERVER['argv'] ?? []
);

$application->run();
