<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMergeMethod.php';

final class TrophyMergeMethodTest extends TestCase
{
    public function testTryFromMixedAcceptsKnownValues(): void
    {
        $this->assertSame(TrophyMergeMethod::Order, TrophyMergeMethod::tryFromMixed('order'));
        $this->assertSame(TrophyMergeMethod::Name, TrophyMergeMethod::tryFromMixed(' Name '));
        $this->assertSame(TrophyMergeMethod::Icon, TrophyMergeMethod::tryFromMixed('ICON'));
        $this->assertSame(TrophyMergeMethod::Order, TrophyMergeMethod::tryFromMixed(TrophyMergeMethod::Order));
    }

    public function testTryFromMixedRejectsUnknownValues(): void
    {
        $this->assertSame(null, TrophyMergeMethod::tryFromMixed('unknown'));
        $this->assertSame(null, TrophyMergeMethod::tryFromMixed([]));
        $this->assertSame(null, TrophyMergeMethod::tryFromMixed(null));
    }

    public function testFromMixedDefaultsMissingValuesToOrder(): void
    {
        $this->assertSame(TrophyMergeMethod::Order, TrophyMergeMethod::fromMixed(null));
        $this->assertSame(TrophyMergeMethod::Order, TrophyMergeMethod::fromMixed(''));
    }

    public function testFromMixedThrowsForUnknownValues(): void
    {
        try {
            TrophyMergeMethod::fromMixed('unknown');
            $this->fail('Expected InvalidArgumentException for unknown merge method.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Wrong input', $exception->getMessage());
        }
    }

    public function testProgressLabel(): void
    {
        $this->assertSame('Matching trophies by list order…', TrophyMergeMethod::Order->progressLabel());
        $this->assertSame('Matching trophies by name…', TrophyMergeMethod::Name->progressLabel());
        $this->assertSame('Matching trophies by icon…', TrophyMergeMethod::Icon->progressLabel());
    }
}
