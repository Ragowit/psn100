<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../classes/SessionManager.php';
require_once __DIR__ . '/../classes/Admin/AdminBootstrap.php';

SessionManager::ensureStarted();

$authService = AdminBootstrap::createAuthService();
$authService->logout();

header('Location: /admin/login.php', true, 303);
exit;
