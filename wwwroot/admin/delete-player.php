<?php

declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/DeletePlayerService.php';
require_once '../classes/Admin/DeletePlayerRequestHandler.php';

$service = new DeletePlayerService($database);
$requestHandler = new DeletePlayerRequestHandler($service);

$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);
$result = $requestHandler->handleRequest($request);
$success = $result->getSuccessMessage();
$error = $result->getErrorMessage();

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ Delete Player</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off" class="mb-4">
                <div class="mb-3">
                    <label for="account-id" class="form-label">Account ID</label>
                    <input type="text" id="account-id" name="account_id" class="form-control" value="<?= htmlspecialchars($_POST['account_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-text">Provide the numeric account ID for the player.</div>
                </div>
                <div class="mb-3">
                    <label for="online-id" class="form-label">Online ID</label>
                    <input type="text" id="online-id" name="online_id" class="form-control" value="<?= htmlspecialchars($_POST['online_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="form-text">If the account ID is unknown, provide the online ID instead.</div>
                </div>
                <p class="text-muted">Enter either an account ID or an online ID. Only one is required.</p>
                <button type="submit" class="btn btn-danger">Delete Player</button>
            </form>

            <?php if ($error !== null) { ?>
                <div class="alert alert-danger" role="alert">
                    <?= $error; ?>
                </div>
            <?php } ?>

            <?php if ($success !== null) { ?>
                <div class="alert alert-success" role="alert">
                    <?= $success; ?>
                </div>
            <?php } ?>
        </div>
    </body>
</html>
