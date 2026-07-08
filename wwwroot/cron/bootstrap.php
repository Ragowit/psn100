<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/classes/Cron/CronCliAccessGuard.php';

CronCliAccessGuard::requireCliExecution();
