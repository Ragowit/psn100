<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once '../classes/Admin/GameDetail.php';
require_once '../classes/Admin/GameDetailService.php';
require_once '../classes/Admin/GameDetailPage.php';
require_once '../classes/Admin/GameDetailPageResult.php';
require_once '../classes/CommaSeparatedValues.php';
require_once '../classes/GameStatusService.php';
require_once '../classes/GameMessageSanitizer.php';

$gameDetailService = new GameDetailService($database);
$gameStatusService = new GameStatusService($database);
$gameDetailPage = new GameDetailPage($gameDetailService, $gameStatusService);
$statusOptions = $gameDetailPage->getStatusOptions();
$platformOptions = $gameDetailPage->getPlatformOptions();
$pageResult = $gameDetailPage->handle($_SERVER ?? [], $_GET ?? [], $_POST ?? []);

$gameDetail = $pageResult->getGameDetail();
$success = $pageResult->getSuccessMessage();
$error = $pageResult->getErrorMessage();

$requestedGameId = isset($_GET['game']) ? (string) $_GET['game'] : '';
$requestedNpCommunicationId = isset($_GET['np_communication_id']) ? (string) $_GET['np_communication_id'] : '';

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="<?= htmlspecialchars(BootstrapAssets::stylesheetUrl(), ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
        <title>Admin ~ Game Details</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="get" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game" value="<?= Html::escape($requestedGameId); ?>"><br>
                NP Communication ID:<br>
                <input type="text" name="np_communication_id" style="width: 300px;" value="<?= Html::escape($requestedNpCommunicationId); ?>"><br>
                <small>Examples: NPWR10853_00, MERGE_048500</small><br>
                <input type="submit" value="Fetch">
            </form>

            <?php if ($gameDetail !== null) { ?>
                <?php
                $gameSlug = $utility->slugify($gameDetail->getName());
                $gameUrl = '/game/' . $gameDetail->getId() . '-' . $gameSlug;
                ?>
                <p>
                    Game page:
                    <a href="<?= Html::escape($gameUrl); ?>" target="_blank" rel="noopener">
                        <?= Html::escape($gameUrl); ?>
                    </a>
                </p>
                <form method="post" autocomplete="off">
                    <?php AdminBootstrap::renderCsrfField(); ?>
                    <input type="hidden" name="action" value="update-detail">
                    <input type="hidden" name="game" value="<?= $gameDetail->getId(); ?>"><br>
                    Name:<br>
                    <input type="text" name="name" style="width: 859px;" value="<?= Html::escape($gameDetail->getName()); ?>"><br>
                    Icon URL:<br>
                    <input type="text" name="icon_url" style="width: 859px;" value="<?= Html::escape($gameDetail->getIconUrl()); ?>"><br>
                    <?php
                    $selectedPlatforms = [];
                    foreach (CommaSeparatedValues::parseUppercaseTrimmed($gameDetail->getPlatform()) as $normalizedPlatform) {
                        $selectedPlatforms[$normalizedPlatform] = true;
                    }
                    ?>
                    <fieldset class="mb-3">
                        <legend class="col-form-label pt-0">Platform:</legend>
                        <?php foreach ($platformOptions as $platformOption) { ?>
                            <?php
                            $lowerCasePlatform = strtolower($platformOption);
                            $isChecked = isset($selectedPlatforms[$platformOption]) ? 'checked' : '';
                            ?>
                            <div class="form-check form-check-inline">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="platform-<?= $lowerCasePlatform; ?>"
                                    name="platform[]"
                                    value="<?= Html::escape($platformOption); ?>"
                                    <?= $isChecked; ?>
                                >
                                <label class="form-check-label" for="platform-<?= $lowerCasePlatform; ?>">
                                    <?= Html::escape($platformOption); ?>
                                </label>
                            </div>
                        <?php } ?>
                    </fieldset>
                    Set Version:<br>
                    <input type="text" name="set_version" style="width: 859px;" value="<?= Html::escape($gameDetail->getSetVersion()); ?>"><br>
                    Region:<br>
                    <input type="text" name="region" style="width: 859px;" value="<?= Html::escape((string) ($gameDetail->getRegion() ?? '')); ?>"><br>
                    NP Communication ID:<br>
                    <input type="text" name="np_communication_id" style="width: 859px;" value="<?= Html::escape((string) ($gameDetail->getNpCommunicationId() ?? '')); ?>" readonly><br>
                    PSNProfiles ID:<br>
                    <input type="text" name="psnprofiles_id" style="width: 859px;" value="<?= Html::escape((string) ($gameDetail->getPsnprofilesId() ?? '')); ?>"><br>
                    Obsolete Game IDs:<br>
                    <input type="text" name="obsolete_ids" style="width: 859px;" value="<?= Html::escape((string) ($gameDetail->getObsoleteIds() ?? '')); ?>"><br>
                    <small>Comma separated trophy_title.id values.</small><br>
                    Message:<br>
                    <textarea name="message" rows="6" cols="120"><?= GameMessageSanitizer::escapeTextareaContent($gameDetail->getMessage()); ?></textarea><br><br>
                    <label for="status">Status:</label><br>
                    <select id="status" name="status" class="form-select" style="max-width: 300px;">
                        <?php foreach ($statusOptions as $value => $label) { ?>
                            <?php $selected = $gameDetail->getStatus()->value === $value ? 'selected' : ''; ?>
                            <option value="<?= Html::escape((string) $value); ?>" <?= $selected; ?>>
                                <?= Html::escape($label); ?>
                            </option>
                        <?php } ?>
                    </select><br><br>
                    <input type="submit" value="Submit">
                </form>
            <?php } ?>

            <?php
            if ($error !== null) {
                echo $error;
            }

            if ($success !== null) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
