<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/NotFoundPage.php';

$notFoundPage = NotFoundPage::createDefault();

$title = $notFoundPage->getTitle();
require_once __DIR__ . '/header.php';
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h1><?= htmlspecialchars($notFoundPage->getHeading(), ENT_QUOTES, 'UTF-8'); ?></h1>
        </div>

        <div class="col-12">
            <p><?= htmlspecialchars($notFoundPage->getMessage(), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
