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

    public function testExecuteWithLegacyTruthUsesRemainingShadowBudgetAfterLegacyExecution(): void
    {
        if (
            !function_exists('pcntl_signal')
            || !function_exists('pcntl_async_signals')
            || !function_exists('pcntl_setitimer')
        ) {
            return;
        }

        $shadowCompleted = 0;

        $result = ShadowExecutionUtility::executeWithLegacyTruth(
            PsnClientMode::fromValue('shadow'),
            'test_operation',
            static function (): array {
                usleep(120_000);

                return ['legacy' => true];
            },
            static function () use (&$shadowCompleted): array {
                usleep(80_000);
                $shadowCompleted++;

                return ['shadow' => true];
            },
            static fn (mixed $value): array => is_array($value) ? $value : [],
            150
        );

        $this->assertSame(['legacy' => true], $result);
        $this->assertSame(0, $shadowCompleted);
    }

    public function testExecuteWithLegacyTruthInNewModeUsesPrimaryExecutorOnly(): void
    {
        $legacyExecutions = 0;
        $shadowExecutions = 0;

        $result = ShadowExecutionUtility::executeWithLegacyTruth(
            PsnClientMode::fromValue('new'),
            'test_operation',
            static function () use (&$legacyExecutions): array {
                $legacyExecutions++;

                return ['primary' => true];
            },
            static function () use (&$shadowExecutions): array {
                $shadowExecutions++;

                return ['shadow' => true];
            },
            static fn (mixed $value): array => is_array($value) ? $value : [],
            350
        );

        $this->assertSame(['primary' => true], $result);
        $this->assertSame(1, $legacyExecutions);
        $this->assertSame(0, $shadowExecutions);
    }

    public function testExecuteWithLegacyTruthEmitsStructuredMismatchEvent(): void
    {
        if (
            !function_exists('pcntl_signal')
            || !function_exists('pcntl_async_signals')
            || !function_exists('pcntl_setitimer')
        ) {
            return;
        }

        ShadowExecutionUtility::resetStateForTests();
        $events = [];
        ShadowExecutionUtility::setEventEmitter(static function (array $event) use (&$events): void {
            $events[] = $event;
        });

        ShadowExecutionUtility::executeWithLegacyTruth(
            PsnClientMode::fromValue('shadow'),
            'player_profile_lookup',
            static fn (): array => ['profile' => ['onlineId' => 'Tester', 'accountId' => '42', 'title' => 'old']],
            static fn (): array => ['profile' => ['onlineId' => 'Tester', 'accountId' => '42', 'title' => 'new']],
            static fn (mixed $value): array => is_array($value) ? $value : [],
            350,
            [
                'service' => 'psn_player_lookup',
                'correlationId' => 'req-123',
                'mismatchSampleRate' => 1,
                'mismatchRateLimitPerMinute' => 100,
            ]
        );

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertSame('psn_shadow_mismatch', $event['event']);
        $this->assertSame('psn_player_lookup', $event['service']);
        $this->assertSame('player_profile_lookup', $event['operation']);
        $this->assertSame('req-123', $event['correlationId']);
        $this->assertSame('req-123', $event['requestId']);
        $this->assertSame('Tester', $event['identifiers']['onlineId']);
        $this->assertSame('42', $event['identifiers']['accountId']);
        $this->assertNull($event['identifiers']['npCommunicationId']);
        $this->assertSame(1, $event['diffSummary']['mismatchCount']);
        $this->assertContains('profile.title', $event['diffSummary']['changedPaths']);
        $this->assertSame(1.0, $event['sampling']['sampleRate']);

        ShadowExecutionUtility::resetStateForTests();
    }

    public function testExecuteWithLegacyTruthAppliesMismatchRateLimit(): void
    {
        if (
            !function_exists('pcntl_signal')
            || !function_exists('pcntl_async_signals')
            || !function_exists('pcntl_setitimer')
        ) {
            return;
        }

        ShadowExecutionUtility::resetStateForTests();
        $events = [];
        ShadowExecutionUtility::setEventEmitter(static function (array $event) use (&$events): void {
            $events[] = $event;
        });

        for ($i = 0; $i < 3; $i++) {
            ShadowExecutionUtility::executeWithLegacyTruth(
                PsnClientMode::fromValue('shadow'),
                'player_profile_lookup',
                static fn (): array => ['profile' => ['title' => 'legacy']],
                static fn (): array => ['profile' => ['title' => 'shadow']],
                static fn (mixed $value): array => is_array($value) ? $value : [],
                350,
                [
                    'service' => 'psn_player_lookup',
                    'correlationId' => 'shared-correlation-id',
                    'mismatchSampleRate' => 1,
                    'mismatchRateLimitPerMinute' => 2,
                ]
            );
        }

        $this->assertCount(2, $events);

        ShadowExecutionUtility::resetStateForTests();
    }

}
