<?php

declare(strict_types=1);

require_once 'init.php';
require_once 'classes/SessionManager.php';
require_once 'classes/CsrfTokenManager.php';
require_once 'classes/HttpMethod.php';
require_once 'classes/PlayerQueueEndpoint.php';
require_once 'classes/PlayerQueueResponse.php';
require_once 'classes/JsonResponseEmitter.php';

$jsonResponder = new JsonResponseEmitter();

if (!HttpMethod::fromServer($_SERVER)->isPost()) {
    $jsonResponder->respond(
        PlayerQueueResponse::error('Queue submissions must use POST.')->toArray(),
        405
    );
    exit;
}

SessionManager::ensureStarted();

$submittedToken = $_POST['_csrf_token'] ?? '';
if (!CsrfTokenManager::validate('public', $submittedToken)) {
    $jsonResponder->respond(
        PlayerQueueResponse::error('Your session has expired. Please reload the page and try again.')->toArray(),
        403
    );
    exit;
}

$endpoint = PlayerQueueEndpoint::fromDatabase($database);
$endpoint->handleAddToQueue($_POST ?? [], $_SERVER ?? []);
