<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../classes/Admin/AdminBootstrap.php';
require_once __DIR__ . '/../classes/BootstrapAssets.php';

AdminBootstrap::requireAuthenticatedAdminPage();
