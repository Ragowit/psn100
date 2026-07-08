<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once '../classes/Admin/AdminRequest.php';
require_once '../classes/Admin/WorkerCredentialRevealHandler.php';
require_once '../classes/Admin/WorkerService.php';

header('Content-Type: application/json; charset=utf-8');

$handler = new WorkerCredentialRevealHandler(new WorkerService($database));
$result = $handler->handle(AdminRequest::fromGlobals($_SERVER ?? [], $_POST ?? []));

http_response_code($result->isSuccess() ? 200 : 400);
echo json_encode($result->toPayload(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
