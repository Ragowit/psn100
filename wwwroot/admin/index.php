<?php

declare(strict_types=1);

require_once '../classes/Admin/AdminNavigation.php';

$navigation = new AdminNavigation();
$navigationItems = $navigation->getItems();
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin</title>
    </head>
    <body>
        <div class="p-4">
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
