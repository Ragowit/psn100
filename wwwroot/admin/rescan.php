<?php

declare(strict_types=1);

require_once("../vendor/autoload.php");
require_once __DIR__ . '/bootstrap.php';
require_once '../classes/StaticAsset.php';
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="<?= htmlspecialchars(BootstrapAssets::stylesheetUrl(), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
        <?php AdminBootstrap::renderCsrfMetaTag(); ?>
        <title>Admin ~ Rescan Game</title>
        <link rel="stylesheet" href="<?= htmlspecialchars(StaticAsset::url('/css/admin-rescan.css'), ENT_QUOTES, 'UTF-8'); ?>">
        <script src="<?= htmlspecialchars(StaticAsset::url('/js/admin-rescan-form.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    </head>
    <body>
        <div class="container py-4">
            <a href="/admin/">Back</a>
            <h1 class="h3 mt-3">Rescan Game</h1>
            <p class="text-body-secondary">Enter the numeric game identifier to trigger a rescan.</p>

            <form id="rescan-form" class="row row-cols-lg-auto g-3 align-items-center" autocomplete="off">
                <div class="col-12">
                    <label class="form-label" for="game">Game ID</label>
                    <input type="text" class="form-control" id="game" name="game" inputmode="numeric" pattern="[0-9]*" required>
                </div>
                <div class="col-12 align-self-end">
                    <button type="submit" class="btn btn-primary" id="rescan-submit">Rescan</button>
                </div>
            </form>

            <div id="progress-wrapper" class="mt-4 d-none">
                <div class="progress">
                    <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">0%</div>
                </div>
                <p id="progress-message" class="text-body-secondary small mt-2">Preparing rescan…</p>
            </div>

            <div id="log-wrapper" class="mt-3 d-none">
                <h2 class="h6 mb-2">Activity log</h2>
                <div id="log-entries" class="border rounded p-2 small font-monospace bg-body-tertiary" style="max-height: 240px; overflow-y: auto;"></div>
            </div>

            <div id="result" class="mt-3"></div>
        </div>
    </body>
</html>
