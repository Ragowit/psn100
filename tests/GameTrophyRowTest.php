<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Game/GameTrophyRow.php';

final class GameTrophyRowTest extends TestCase
{
    private function createRow(array $data, bool $usesPlayStation5Assets = false): GameTrophyRow
    {
        $defaults = [
            'id' => 1,
            'order_id' => 1,
            'type' => 'bronze',
            'name' => 'Example Trophy',
            'detail' => 'Complete the tutorial.',
            'icon_url' => 'icon.png',
            'rarity_percent' => 12.5,
            'in_game_rarity_percent' => 0.0,
            'status' => 0,
        ];

        return GameTrophyRow::fromArray($data + $defaults, new Utility(), $usesPlayStation5Assets);
    }

    public function testGetIconPathUsesPlatformSpecificPlaceholder(): void
    {
        $ps5Row = $this->createRow(['icon_url' => '.png'], true);
        $ps4Row = $this->createRow(['icon_url' => '.png'], false);

        $this->assertSame('../missing-ps5-game-and-trophy.png', $ps5Row->getIconPath());
        $this->assertSame('../missing-ps4-trophy.png', $ps4Row->getIconPath());
    }

    public function testRowAttributesIncludeWarningForUnobtainableTrophy(): void
    {
        $row = $this->createRow(['status' => 1]);

        $attributes = $row->getRowAttributes(null);

        $this->assertStringContainsString('class="table-warning"', $attributes);
        $this->assertStringContainsString('title="', $attributes);
    }

    public function testRowAttributesIncludeSuccessForEarnedTrophy(): void
    {
        $row = $this->createRow(['earned' => 1]);

        $attributes = $row->getRowAttributes(123);

        $this->assertSame(' class="table-success"', $attributes);
    }

    public function testTimestampHelpersDetectMissingTimestamp(): void
    {
        $row = $this->createRow(['earned' => 1, 'earned_date' => 'No Timestamp']);

        $this->assertFalse($row->hasRecordedEarnedDate());
        $this->assertTrue($row->shouldDisplayNoTimestampMessage());
    }

    public function testProgressDisplayUsesZeroWhenProgressMissing(): void
    {
        $row = $this->createRow(['progress_target_value' => 25]);

        $this->assertSame('0/25', $row->getProgressDisplay());
    }

    public function testProgressDisplayUsesTargetWhenEarnedWithoutProgress(): void
    {
        $row = $this->createRow(['progress_target_value' => 15, 'earned' => 1]);

        $this->assertSame('15/15', $row->getProgressDisplay());
    }

    public function testInGameRarityPercentUsesStoredValue(): void
    {
        $row = $this->createRow([
            'in_game_rarity_percent' => 12.5,
        ]);

        $this->assertSame(12.5, $row->getInGameRarityPercent());
    }

    public function testInGameRarityPercentReturnsZeroWhenMissing(): void
    {
        $row = $this->createRow([
            'in_game_rarity_percent' => 0.0,
        ]);

        $this->assertSame(0.0, $row->getInGameRarityPercent());
    }
}
