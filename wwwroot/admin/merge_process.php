<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once '../classes/Admin/TrophyMergeProcessor.php';

$processor = TrophyMergeProcessor::fromDatabase($database);
$processor->processRequest($_POST ?? [], $_SERVER ?? []);
