<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMetaStatus.php';
require_once __DIR__ . '/../wwwroot/classes/ChangelogEntry.php';

final class TrophyMetaStatusTest extends TestCase
{
    public function testFromValueFallsBackToObtainable(): void
    {
        $this->assertSame(TrophyMetaStatus::Obtainable, TrophyMetaStatus::fromValue(0));
        $this->assertSame(TrophyMetaStatus::Unobtainable, TrophyMetaStatus::fromValue(1));
        $this->assertSame(TrophyMetaStatus::Obtainable, TrophyMetaStatus::fromValue(99));
    }

    public function testFromMixedAcceptsEnumAndScalars(): void
    {
        $this->assertSame(
            TrophyMetaStatus::Unobtainable,
            TrophyMetaStatus::fromMixed(TrophyMetaStatus::Unobtainable)
        );
        $this->assertSame(TrophyMetaStatus::Unobtainable, TrophyMetaStatus::fromMixed('1'));
        $this->assertSame(TrophyMetaStatus::Obtainable, TrophyMetaStatus::fromMixed(null));
    }

    public function testLabelAndChangeType(): void
    {
        $this->assertSame('unobtainable', TrophyMetaStatus::Unobtainable->label());
        $this->assertSame('obtainable', TrophyMetaStatus::Obtainable->label());
        $this->assertSame(
            ChangelogEntryType::GAME_UNOBTAINABLE,
            TrophyMetaStatus::Unobtainable->changeType()
        );
        $this->assertSame(
            ChangelogEntryType::GAME_OBTAINABLE,
            TrophyMetaStatus::Obtainable->changeType()
        );
    }
}
