<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../classes/SessionManager.php';
require_once __DIR__ . '/../classes/HttpMethod.php';
require_once __DIR__ . '/../classes/Admin/AdminBootstrap.php';

SessionManager::ensureStarted();

if (!HttpMethod::fromServer($_SERVER)->isPost()) {
    http_response_code(405);
    echo 'Method not allowed.';
    exit;
}

AdminBootstrap::requireValidPostCsrfToken();

$authService = AdminBootstrap::createAuthService();
$authService->logout();

header('Location: /admin/login.php', true, 303);
exit;
