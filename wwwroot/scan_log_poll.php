<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/classes/AboutPageService.php';
require_once __DIR__ . '/classes/JsonResponseEmitter.php';
require_once __DIR__ . '/classes/AboutPageScanLogController.php';

$aboutPageService = new AboutPageService($database, $utility);
$jsonResponder = new JsonResponseEmitter();
$controller = AboutPageScanLogController::create($aboutPageService, $jsonResponder);

$controller->handle($_GET ?? []);
