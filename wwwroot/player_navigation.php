<?php

declare(strict_types=1);

require_once __DIR__ . '/classes/PlayerNavigationRenderer.php';

if (!isset($playerNavigation) || !$playerNavigation instanceof PlayerNavigation) {
    throw new RuntimeException('Player navigation data is missing.');
}

$renderer = new PlayerNavigationRenderer();

echo $renderer->render($playerNavigation);
