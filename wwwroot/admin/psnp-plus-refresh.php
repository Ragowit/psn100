<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../classes/PsnpPlusClient.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /admin/psnp-plus.php');
    exit;
}

$psnpPlusClient = new PsnpPlusClient();
$cacheFilePath = $psnpPlusClient->getCacheFilePath();

if ($cacheFilePath === null) {
    $query = http_build_query(['error' => 'PSNP+ cache path is not configured. Update config/psnp-plus.php.']);
    header('Location: /admin/psnp-plus.php?' . $query);
    exit;
}

try {
    $psnpPlusClient->downloadToPath($cacheFilePath);
    $query = http_build_query(['success' => 'PSNP+ cache refreshed successfully.']);
} catch (RuntimeException $exception) {
    $query = http_build_query(['error' => $exception->getMessage()]);
}

header('Location: /admin/psnp-plus.php?' . $query);
exit;
