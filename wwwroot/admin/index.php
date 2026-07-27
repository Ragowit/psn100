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
        <link href="<?= Html::escape(BootstrapAssets::stylesheetUrl()); ?>" rel="stylesheet">
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
                    echo ' (' . Html::escape($authenticatedUsername) . ')';
                }
                ?>
            </p>
            <ul>
                <?php foreach ($navigationItems as $item) { ?>
                    <li>
                        <a href="<?= Html::escape($item->getHref()); ?>">
                            <?= Html::escape($item->getLabel()); ?>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </body>
</html>
