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
$confirmation = $result->getConfirmation();

$accountIdValue = $_POST['account_id'] ?? '';

if (!is_string($accountIdValue)) {
    $accountIdValue = '';
}

$onlineIdValue = $_POST['online_id'] ?? '';

if (!is_string($onlineIdValue)) {
    $onlineIdValue = '';
}

if ($confirmation !== null) {
    $accountIdValue = $confirmation->getAccountId();
    $onlineIdValue = $confirmation->getOnlineId() ?? '';
}

$encodedAccountIdValue = htmlspecialchars($accountIdValue, ENT_QUOTES, 'UTF-8');
$encodedOnlineIdValue = htmlspecialchars($onlineIdValue, ENT_QUOTES, 'UTF-8');

$confirmationOnlineId = $confirmation?->getOnlineId();
$confirmationDisplayName = $confirmationOnlineId ?? $accountIdValue;
$confirmationUrl = $confirmationOnlineId !== null
    ? 'https://psn100.net/player/' . rawurlencode($confirmationOnlineId)
    : null;
$confirmationAccountId = $confirmation?->getAccountId();
$encodedConfirmationAccountId = $confirmationAccountId === null ? '' : htmlspecialchars($confirmationAccountId, ENT_QUOTES, 'UTF-8');
$encodedConfirmationOnlineId = $confirmationOnlineId === null ? '' : htmlspecialchars($confirmationOnlineId, ENT_QUOTES, 'UTF-8');
$encodedConfirmationUrl = $confirmationUrl === null ? null : htmlspecialchars($confirmationUrl, ENT_QUOTES, 'UTF-8');
$encodedConfirmationDisplayName = htmlspecialchars($confirmationDisplayName, ENT_QUOTES, 'UTF-8');

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
                    <input type="text" id="account-id" name="account_id" class="form-control" value="<?= $encodedAccountIdValue; ?>">
                    <div class="form-text">Provide the numeric account ID for the player.</div>
                </div>
                <div class="mb-3">
                    <label for="online-id" class="form-label">Online ID</label>
                    <input type="text" id="online-id" name="online_id" class="form-control" value="<?= $encodedOnlineIdValue; ?>">
                    <div class="form-text">If the account ID is unknown, provide the online ID instead.</div>
                </div>
                <p class="text-muted">Enter either an account ID or an online ID. Only one is required.</p>
                <button type="submit" class="btn btn-danger">Delete Player</button>
            </form>

            <?php if ($confirmation !== null) { ?>
                <div class="alert alert-warning" role="alert">
                    <p>
                        Are you sure you want to permanently delete player
                        <?php
                        if ($encodedConfirmationUrl !== null) {
                            printf(
                                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                                $encodedConfirmationUrl,
                                $encodedConfirmationDisplayName
                            );
                        } else {
                            printf('<strong>%s</strong>', $encodedConfirmationDisplayName);
                        }

                        echo '?';
                        ?>
                    </p>
                    <p class="mb-0">This action cannot be undone.</p>
                </div>
                <form method="post" autocomplete="off" class="mb-4">
                    <input type="hidden" name="account_id" value="<?= $encodedConfirmationAccountId; ?>">
                    <input type="hidden" name="online_id" value="<?= $encodedConfirmationOnlineId; ?>">
                    <input type="hidden" name="confirm_delete" value="1">
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                </form>
            <?php } ?>

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
