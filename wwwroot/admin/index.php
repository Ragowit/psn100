<?php

declare(strict_types=1);

require_once '../classes/Admin/AdminNavigation.php';
require_once '../classes/Admin/AdminLayoutRenderer.php';

$navigation = new AdminNavigation();
$navigationItems = $navigation->getItems();

$layoutRenderer = new AdminLayoutRenderer();
$options = AdminLayoutOptions::create()->withBackLink(false);

echo $layoutRenderer->render('Admin', static function () use ($navigationItems): void {
    ?>
    <ul>
        <?php foreach ($navigationItems as $item) { ?>
            <li>
                <a href="<?= htmlspecialchars($item->getHref(), ENT_QUOTES, 'UTF-8'); ?>">
                    <?= htmlspecialchars($item->getLabel(), ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </li>
        <?php } ?>
    </ul>
    <?php
}, $options);
