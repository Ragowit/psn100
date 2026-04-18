<?php

declare(strict_types=1);

return [
    'psn' => [
        'client_mode' => $_ENV['PSN_CLIENT_MODE'] ?? getenv('PSN_CLIENT_MODE') ?: 'legacy',
    ],
];
