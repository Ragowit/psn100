<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/ExecutionEnvironmentConfigurator.php';

ExecutionEnvironmentConfigurator::create()
    ->addIniSetting('max_execution_time', '0')
    ->addIniSetting('max_input_time', '0')
    ->addIniSetting('mysql.connect_timeout', '0')
    ->enableUnlimitedExecution()
    ->configure();

require_once __DIR__ . '/bootstrap.php';
require_once '../classes/StaticAsset.php';
require_once("../classes/TrophyMergeService.php");
require_once("../classes/Admin/TrophyMergeRequestHandler.php");

$mergeService = new TrophyMergeService($database);
$requestHandler = new TrophyMergeRequestHandler($mergeService);
$message = $requestHandler->handle($_POST ?? []);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <?php AdminBootstrap::renderCsrfMetaTag(); ?>
        <title>Admin ~ Merge Games</title>
        <script src="<?= htmlspecialchars(StaticAsset::url('/js/admin-merge-form.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form id="merge-form" method="post" autocomplete="off" class="row g-3">
                    <?php AdminBootstrap::renderCsrfField(); ?>
                <div class="row">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-parent">Game Parent ID</label>
                        <input type="number" class="form-control" id="merge-parent" name="parent" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-child">Game Child ID</label>
                        <input type="number" class="form-control" id="merge-child" name="child" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-method">Method</label>
                        <select class="form-select" id="merge-method" name="method">
                            <option value="order">Order</option>
                            <option value="name">Name</option>
                            <option value="icon">Icon</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-trophy-parent">Trophy Parent ID</label>
                        <input type="number" class="form-control" id="merge-trophy-parent" name="trophyparent" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-trophy-child">Trophy Child ID</label>
                        <input type="text" class="form-control" id="merge-trophy-child" name="trophychild">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="merge-clone">Clone Game ID</label>
                        <input type="number" class="form-control" id="merge-clone" name="clone" inputmode="numeric">
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 align-self-end">
                        <button type="submit" class="btn btn-primary" id="merge-submit">Submit</button>
                    </div>
                </div>
            </form>

            <div id="merge-progress-wrapper" class="mt-4 d-none">
                <div class="progress">
                    <div id="merge-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">0%</div>
                </div>
                <p id="merge-progress-message" class="text-body-secondary small mt-2">Preparing game merge…</p>
            </div>

            <div id="merge-result" class="mt-3">
                <?= $message; ?>
            </div>
        </div>
    </body>
</html>
