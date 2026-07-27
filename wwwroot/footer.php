<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/Html.php';

require_once __DIR__ . '/classes/FooterViewModel.php';
require_once __DIR__ . '/classes/FooterRenderer.php';
require_once __DIR__ . '/classes/BootstrapAssets.php';

$footerViewModel = FooterViewModel::createDefault();
$footerRenderer = new FooterRenderer();

echo $footerRenderer->render($footerViewModel);
?>
        
        <!-- Popper.js, then Bootstrap JS -->
        <script src="<?= Html::escape(BootstrapAssets::popperScriptUrl()); ?>"></script>
        <script src="<?= Html::escape(BootstrapAssets::scriptUrl()); ?>"></script>
    </body>
</html>
