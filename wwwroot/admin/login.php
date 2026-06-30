<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../classes/SessionManager.php';
require_once __DIR__ . '/../classes/CsrfTokenManager.php';
require_once __DIR__ . '/../classes/Admin/AdminBootstrap.php';

SessionManager::ensureStarted();

$authService = AdminBootstrap::createAuthService();
$errorMessage = null;

if ($authService->isConfigured() && $authService->isAuthenticated()) {
    header('Location: /admin/', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['_csrf_token'] ?? '';
    if (!CsrfTokenManager::validate('admin', $submittedToken)) {
        http_response_code(403);
        echo 'Invalid CSRF token.';
        exit;
    }

    $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
    $password = is_string($_POST['password'] ?? null) ? (string) $_POST['password'] : '';

    if (!$authService->isConfigured()) {
        $errorMessage = 'Admin access is not configured.';
    } elseif ($authService->login($username, $password)) {
        header('Location: /admin/', true, 303);
        exit;
    } else {
        $errorMessage = 'Invalid username or password.';
    }
}

$isConfigured = $authService->isConfigured();
$encodedErrorMessage = $errorMessage === null ? null : htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin Login</title>
    </head>
    <body>
        <div class="p-4" style="max-width: 28rem;">
            <h1 class="h3 mb-3">Admin Login</h1>

            <?php if (!$isConfigured) { ?>
                <div class="alert alert-warning" role="alert">
                    Admin access is not configured. Set <code>ADMIN_USERNAME</code> and
                    <code>ADMIN_PASSWORD</code> or <code>ADMIN_PASSWORD_HASH</code>.
                </div>
            <?php } else { ?>
                <?php if ($encodedErrorMessage !== null) { ?>
                    <div class="alert alert-danger" role="alert">
                        <?= $encodedErrorMessage; ?>
                    </div>
                <?php } ?>

                <form method="post" autocomplete="off">
                    <?= CsrfTokenManager::hiddenField('admin'); ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Sign in</button>
                </form>
            <?php } ?>
        </div>
    </body>
</html>
