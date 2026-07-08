<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/Cron/CronCliAccessGuard.php';

CronCliAccessGuard::denyWebCronScriptAccess($_SERVER ?? []);

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: SAMEORIGIN');
header(
    "Content-Security-Policy-Report-Only: default-src 'self'; "
    . "script-src 'self' 'unsafe-inline'; "
    . "style-src 'self' 'unsafe-inline'; "
    . "img-src 'self' data: https:; "
    . "font-src 'self'; "
    . "connect-src 'self'; "
    . "frame-ancestors 'self'; "
    . "base-uri 'self'; "
    . "form-action 'self'"
);

require_once __DIR__ . '/classes/ApplicationBootstrapper.php';

$applicationBootstrapper = ApplicationBootstrapper::bootstrap();
$applicationContainer = $applicationBootstrapper->getApplicationContainer();

$database = $applicationBootstrapper->getDatabase();
$utility = $applicationBootstrapper->getUtility();
$paginationRenderer = $applicationBootstrapper->getPaginationRenderer();
