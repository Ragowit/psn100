<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/FooterViewModel.php';
require_once __DIR__ . '/classes/FooterRenderer.php';
require_once __DIR__ . '/classes/BootstrapAssets.php';

$footerViewModel = FooterViewModel::createDefault();
$footerRenderer = new FooterRenderer();

echo $footerRenderer->render($footerViewModel);
?>
        
        <!-- Popper.js, then Bootstrap JS -->
        <script src="<?= htmlspecialchars(BootstrapAssets::popperScriptUrl(), ENT_QUOTES, 'UTF-8'); ?>"></script>
        <script src="<?= htmlspecialchars(BootstrapAssets::scriptUrl(), ENT_QUOTES, 'UTF-8'); ?>"></script>
    </body>
</html>
