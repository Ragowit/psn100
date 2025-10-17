<?php

declare(strict_types=1);

require_once '../vendor/autoload.php';
require_once '../init.php';
require_once '../classes/Admin/GameRescanProcessor.php';

$processor = GameRescanProcessor::fromDatabase($database);
$processor->processRequest($_POST ?? [], $_SERVER ?? []);
