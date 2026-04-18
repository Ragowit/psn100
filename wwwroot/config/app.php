<?php

declare(strict_types=1);

$psnClientMode = $_ENV['PSN_CLIENT_MODE'] ?? getenv('PSN_CLIENT_MODE');

return [
    'psn' => [
        'client_mode' => $psnClientMode === false ? 'legacy' : $psnClientMode,
    ],
];
