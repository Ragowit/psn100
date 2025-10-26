<?php
declare(strict_types=1);

require_once '../init.php';
require_once '../classes/Admin/GameStatusRequestHandler.php';
require_once '../classes/Admin/GameStatusPage.php';

$gameStatusService = new GameStatusService($database);
$requestHandler = new GameStatusRequestHandler($gameStatusService);

$request = AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []);
$result = $requestHandler->handleRequest($request);

$page = GameStatusPage::fromResult($result);

echo $page->render();
