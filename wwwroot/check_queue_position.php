<?php

declare(strict_types=1);

require_once 'init.php';
require_once 'classes/SessionManager.php';
require_once 'classes/PlayerQueueEndpoint.php';

SessionManager::ensureStarted();

$endpoint = PlayerQueueEndpoint::fromDatabase($database);
$endpoint->handleQueuePosition($_GET ?? [], $_SERVER ?? []);
