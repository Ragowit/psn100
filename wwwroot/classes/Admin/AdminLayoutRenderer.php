<?php

declare(strict_types=1);

require_once __DIR__ . '/AdminLayoutOptions.php';

final class AdminLayoutRenderer
{
    private const BOOTSTRAP_STYLESHEET = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';

    private const BOOTSTRAP_INTEGRITY = 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH';

    /**
     * @param callable():void $bodyRenderer
     */
    public function render(
        string $title,
        callable $bodyRenderer,
        ?AdminLayoutOptions $options = null
    ): string {
        $options ??= AdminLayoutOptions::create();

        ob_start();
        ?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="<?= htmlspecialchars(self::BOOTSTRAP_STYLESHEET, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet" integrity="<?= htmlspecialchars(self::BOOTSTRAP_INTEGRITY, ENT_QUOTES, 'UTF-8'); ?>" crossorigin="anonymous">
        <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    </head>
    <body>
        <div class="<?= htmlspecialchars($options->getContainerClass(), ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($options->shouldShowBackLink()) { ?>
                <a href="/admin/">Back</a><br><br>
            <?php } ?>
            <?php $bodyRenderer(); ?>
        </div>
    </body>
</html>
<?php
        $output = ob_get_clean();

        return $output === false ? '' : $output;
    }
}
