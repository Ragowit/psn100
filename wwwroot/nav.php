<?php
require_once __DIR__ . '/classes/NavigationBarRenderer.php';

$navigationState = NavigationState::fromGlobals($_SERVER ?? [], $_GET ?? []);
$navigationBarRenderer = NavigationBarRenderer::create($navigationState);

echo $navigationBarRenderer->render();
