<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/Html.php';

require_once __DIR__ . '/classes/NotFoundPage.php';

$notFoundPage = NotFoundPage::createDefault();

$title = $notFoundPage->getTitle();
require_once __DIR__ . '/header.php';
?>

<main class="container">
    <div class="row">
        <div class="col-12">
            <h1><?= Html::escape($notFoundPage->getHeading()); ?></h1>
        </div>

        <div class="col-12">
            <p><?= Html::escape($notFoundPage->getMessage()); ?></p>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/footer.php';
?>
