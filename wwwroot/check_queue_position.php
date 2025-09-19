<?php

require_once 'init.php';
require_once 'classes/PlayerQueueService.php';
require_once 'classes/PlayerQueueHandler.php';

$playerQueueService = new PlayerQueueService($database);
$playerQueueHandler = new PlayerQueueHandler($playerQueueService);

echo $playerQueueHandler->handleQueuePositionRequest($_REQUEST, $_SERVER);
