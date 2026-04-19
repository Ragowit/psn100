<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PsnClientMode.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Shadow/ShadowExecutionUtility.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Shadow/ShadowResponseNormalizer.php';

final class ShadowPlayStationUtilityTest extends TestCase
{
    public function testCanonicalizePreservesDigitOnlyStringsByDefault(): void
    {
        $normalized = ShadowResponseNormalizer::canonicalize([
            'onlineId' => '000123',
            'npId' => '0123456789',
        ]);

        $this->assertSame('000123', $normalized['onlineId']);
        $this->assertSame('0123456789', $normalized['npId']);
    }

    public function testNormalizePlayerProfileLookupPreservesStringIdentifiers(): void
    {
        $normalized = ShadowResponseNormalizer::normalizePlayerProfileLookup((object) [
            'profile' => (object) [
                'accountId' => '42',
                'onlineId' => '000123',
                'currentOnlineId' => '001122',
                'npId' => '0000000001',
            ],
        ]);

        $this->assertSame(42, $normalized['profile']['accountId']);
        $this->assertSame('000123', $normalized['profile']['onlineId']);
        $this->assertSame('001122', $normalized['profile']['currentOnlineId']);
        $this->assertSame('0000000001', $normalized['profile']['npId']);
    }

    public function testExecuteWithLegacyTruthSkipsShadowWhenTimeoutSupportIsUnavailable(): void
    {
        if (
            function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals')
            && function_exists('pcntl_setitimer')
        ) {
            return;
        }

        $shadowExecutions = 0;

        $result = ShadowExecutionUtility::executeWithLegacyTruth(
            PsnClientMode::fromValue('shadow'),
            'test_operation',
            static fn (): array => ['legacy' => true],
            static function () use (&$shadowExecutions): array {
                $shadowExecutions++;

                return ['shadow' => true];
            },
            static fn (mixed $value): array => is_array($value) ? $value : [],
            350
        );

        $this->assertSame(['legacy' => true], $result);
        $this->assertSame(0, $shadowExecutions);
    }
}
