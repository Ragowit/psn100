<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PsnClientMode.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Shadow/ShadowPromotionPolicy.php';

final class PsnShadowPromotionPolicyTest extends TestCase
{
    public function testServiceModeOverrideUsesEnvironmentConfiguration(): void
    {
        putenv('PSN_CLIENT_MODE=legacy');
        putenv('PSN_CLIENT_MODE_OVERRIDES_JSON={"psn_player_lookup":"shadow","psn_game_lookup":"new"}');

        $this->assertSame('shadow', PsnClientMode::forService('psn_player_lookup')->value());
        $this->assertSame('new', PsnClientMode::forService('psn_game_lookup')->value());
        $this->assertSame('legacy', PsnClientMode::forService('unknown_service')->value());

        putenv('PSN_CLIENT_MODE_OVERRIDES_JSON');
        putenv('PSN_CLIENT_MODE');
    }

    public function testPromotionPolicyPassesWhenAllThresholdsPass(): void
    {
        $policy = new ShadowPromotionPolicy([
            'thresholds' => [
                'default' => [
                    'maxMismatchRate' => ['1h' => 0.03],
                    'minCompared' => ['1h' => 100],
                    'maxNewClientErrorRate' => ['1h' => 0.02],
                    'maxNormalizationSkipRate' => ['1h' => 0.01],
                ],
            ],
        ]);

        $result = $policy->evaluate('psn_player_lookup', 'player_profile_lookup', [
            '1h' => [
                'totalCompared' => 200,
                'matched' => 196,
                'mismatched' => 4,
                'skippedNormalizationFailure' => 1,
                'newClientErrors' => 2,
            ],
        ]);

        $this->assertTrue($result['promote']);
        $this->assertCount(0, $result['reasons']);
    }

    public function testPromotionPolicyFailsWhenOperationOverrideThresholdFails(): void
    {
        $policy = new ShadowPromotionPolicy([
            'thresholds' => [
                'default' => [
                    'maxMismatchRate' => ['1h' => 0.03],
                    'minCompared' => ['1h' => 100],
                ],
                'services' => [
                    'psn_player_lookup' => [
                        'operations' => [
                            'player_profile_lookup' => [
                                'maxMismatchRate' => ['1h' => 0.01],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $result = $policy->evaluate('psn_player_lookup', 'player_profile_lookup', [
            '1h' => [
                'totalCompared' => 200,
                'matched' => 194,
                'mismatched' => 6,
                'skippedNormalizationFailure' => 0,
                'newClientErrors' => 0,
            ],
        ]);

        $this->assertFalse($result['promote']);
        $this->assertSame('maxMismatchRate', $result['checks'][0]['metric']);
        $this->assertTrue(count($result['reasons']) >= 1);
    }
}
