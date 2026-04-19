<?php

declare(strict_types=1);

$psnClientMode = $_ENV['PSN_CLIENT_MODE'] ?? getenv('PSN_CLIENT_MODE');

return [
    'psn' => [
        'client_mode' => $psnClientMode === false ? 'legacy' : $psnClientMode,
        'client_mode_overrides' => [],
        'shadow_promotion_policy' => [
            'thresholds' => [
                'default' => [
                    'maxMismatchRate' => [
                        '1h' => 0.02,
                        '24h' => 0.01,
                        '7d' => 0.005,
                    ],
                    'minCompared' => [
                        '1h' => 200,
                        '24h' => 2_000,
                        '7d' => 10_000,
                    ],
                    'maxNewClientErrorRate' => [
                        '1h' => 0.01,
                        '24h' => 0.005,
                        '7d' => 0.003,
                    ],
                    'maxNormalizationSkipRate' => [
                        '1h' => 0.002,
                        '24h' => 0.001,
                        '7d' => 0.001,
                    ],
                ],
                'services' => [],
            ],
        ],
    ],
];
