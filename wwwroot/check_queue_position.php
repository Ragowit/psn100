<?php

declare(strict_types=1);

require_once 'init.php';
require_once 'classes/PlayerQueueEndpoint.php';

$endpoint = PlayerQueueEndpoint::fromDatabase($database);
$endpoint->handleQueuePosition($_GET ?? [], $_SERVER ?? []);
