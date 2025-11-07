<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../vendor/autoload.php';
require_once '../classes/Admin/PsnPlayerSearchService.php';

$searchTerm = isset($_GET['player']) ? (string) $_GET['player'] : '';
$normalizedSearchTerm = trim($searchTerm);
$results = [];
$errorMessage = null;

if ($normalizedSearchTerm !== '') {
    try {
        $searchService = PsnPlayerSearchService::fromDatabase($database);
        $results = $searchService->search($normalizedSearchTerm);
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
        <title>Admin ~ PSN Player Search</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <h1 class="h3 mb-3">PSN Player Search</h1>
            <p class="text-body-secondary">Displays up to <?= PsnPlayerSearchService::getResultLimit(); ?> results directly from Sony PSN.</p>
            <form method="get" class="mb-4" action="">
                <div class="mb-2">
                    <label for="player" class="form-label">Player name</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="player" name="player" value="<?= htmlentities($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" maxlength="16" placeholder="PSN player name" autocomplete="off">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </div>
            </form>
            <?php if ($errorMessage !== null) { ?>
                <div class="alert alert-warning" role="alert">
                    <?= htmlentities($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } elseif ($normalizedSearchTerm !== '' && $results === []) { ?>
                <div class="alert alert-info" role="alert">
                    No results found for "<?= htmlentities($normalizedSearchTerm, ENT_QUOTES, 'UTF-8'); ?>".
                </div>
            <?php } ?>
            <?php if ($results !== []) { ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Online ID</th>
                                <th scope="col">Account ID</th>
                                <th scope="col">Country</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $index => $result) { ?>
                                <tr>
                                    <th scope="row"><?= $index + 1; ?></th>
                                    <td><?= htmlentities($result->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlentities($result->getAccountId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlentities($result->getCountry(), ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </body>
</html>
