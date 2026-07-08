<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../classes/Admin/AdminNavigation.php';

$navigation = new AdminNavigation();
$navigationItems = $navigation->getItems();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="<?= htmlspecialchars(BootstrapAssets::stylesheetUrl(), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
        <title>Admin</title>
    </head>
    <body>
        <div class="p-4">
            <p>
                <form method="post" action="/admin/logout.php" class="d-inline">
                    <?php AdminBootstrap::renderCsrfField(); ?>
                    <button type="submit" class="btn btn-link p-0 align-baseline">Log out</button>
                </form>
                <?php
                $authenticatedUsername = AdminBootstrap::createAuthService()->getAuthenticatedUsername();
                if ($authenticatedUsername !== null) {
                    echo ' (' . htmlspecialchars($authenticatedUsername, ENT_QUOTES, 'UTF-8') . ')';
                }
                ?>
            </p>
            <ul>
                <?php foreach ($navigationItems as $item) { ?>
                    <li>
                        <a href="<?= htmlspecialchars($item->getHref(), ENT_QUOTES, 'UTF-8'); ?>">
                            <?= htmlspecialchars($item->getLabel(), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </body>
</html>
