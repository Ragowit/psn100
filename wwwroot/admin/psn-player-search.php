<?php
declare(strict_types=1);

require_once __DIR__ . '/../classes/Admin/PsnPlayerSearchService.php';

$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require_once $vendorAutoload;
}

if (!isset($psnPlayerSearchService)) {
    require_once __DIR__ . '/../init.php';
    $psnPlayerSearchService = PsnPlayerSearchService::fromDatabase($database);
}

$searchTerm = isset($_GET['player']) ? (string) $_GET['player'] : '';
$normalizedSearchTerm = trim($searchTerm);
$results = [];
$errorMessage = null;

if ($normalizedSearchTerm !== '') {
    try {
        $results = $psnPlayerSearchService->search($normalizedSearchTerm);
    } catch (PsnPlayerSearchRateLimitException $exception) {
        $retryAt = $exception->getRetryAt()->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $formattedRetryAt = $retryAt->format('Y-m-d H:i:s T');
        $errorMessage = sprintf('PSN search rate limited until %s. Please wait before retrying.', $formattedRetryAt);
    } catch (Throwable $exception) {
        $message = trim($exception->getMessage());

        if ($message === '') {
            $message = 'An unexpected error occurred while searching for players. Please try again later.';
        }

        $errorMessage = $message;
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
                                <th scope="col">Avatar URL</th>
                                <th scope="col">Avatars</th>
                                <th scope="col">PS Plus</th>
                                <th scope="col">About Me</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $index => $result) { ?>
                                <tr>
                                    <th scope="row"><?= $index + 1; ?></th>
                                    <td><?= htmlentities($result->getOnlineId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?= htmlentities($result->getAccountId(), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <?php if ($result->getCountry() !== '') { ?>
                                            <?= htmlentities($result->getCountry(), ENT_QUOTES, 'UTF-8'); ?>
                                        <?php } else { ?>
                                            <span class="text-body-secondary">-</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($result->getAvatarUrl() !== '') { ?>
                                            <a href="<?= htmlentities($result->getAvatarUrl(), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener" class="link-light">View</a>
                                        <?php } else { ?>
                                            <span class="text-body-secondary">-</span>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php $avatars = $result->getAvatars(); ?>
                                        <?php if ($avatars === []) { ?>
                                            <span class="text-body-secondary">-</span>
                                        <?php } else { ?>
                                            <ul class="list-unstyled mb-0 small">
                                                <?php foreach ($avatars as $size => $url) { ?>
                                                    <li>
                                                        <span class="text-body-secondary text-uppercase"><?= htmlentities((string) $size, ENT_QUOTES, 'UTF-8'); ?>:</span>
                                                        <a href="<?= htmlentities($url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noreferrer noopener" class="link-light">link</a>
                                                    </li>
                                                <?php } ?>
                                            </ul>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($result->isPlus()) { ?>
                                            <span class="badge text-bg-success">Yes</span>
                                        <?php } else { ?>
                                            <span class="text-body-secondary">No</span>
                                        <?php } ?>
                                    </td>
                                    <td class="text-break">
                                        <?php if ($result->getAboutMe() !== '') { ?>
                                            <span class="small"><?= nl2br(htmlentities($result->getAboutMe(), ENT_QUOTES, 'UTF-8'), false); ?></span>
                                        <?php } else { ?>
                                            <span class="text-body-secondary">-</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </body>
</html>
