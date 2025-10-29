<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanDifferenceTracker.php';

final class GameRescanDifferenceTrackerTest extends TestCase
{
    public function testRecordTitleChangeAddsDifferenceWhenValuesDiffer(): void
    {
        $tracker = new GameRescanDifferenceTracker();

        $tracker->recordTitleChange('Name', 'Old Name', 'New Name');

        $this->assertSame([
            [
                'context' => 'Trophy Title',
                'field' => 'Name',
                'previous' => 'Old Name',
                'current' => 'New Name',
            ],
        ], $tracker->getDifferences());
    }

    public function testRecordTitleChangeSkipsRecordingWhenValuesNormalizeToSameResult(): void
    {
        $tracker = new GameRescanDifferenceTracker();

        $tracker->recordTitleChange('Description', '', null);
        $tracker->recordTitleChange('Subtitle', null, '');

        $this->assertSame([], $tracker->getDifferences());
    }

    public function testRecordGroupChangeUsesGroupLabelWhenProvided(): void
    {
        $tracker = new GameRescanDifferenceTracker();

        $tracker->recordGroupChange('NPWR12345', 'Main Group', 'Label', 'Gold', 'Platinum');

        $this->assertSame([
            [
                'context' => 'Group "Main Group" (NPWR12345)',
                'field' => 'Label',
                'previous' => 'Gold',
                'current' => 'Platinum',
            ],
        ], $tracker->getDifferences());
    }

    public function testRecordGroupChangeUsesGroupIdWhenLabelMissingAndNormalizesBooleanValues(): void
    {
        $tracker = new GameRescanDifferenceTracker();

        $tracker->recordGroupChange('NPWR54321', '', 'IsHidden', false, true);

        $this->assertSame([
            [
                'context' => 'Group NPWR54321',
                'field' => 'IsHidden',
                'previous' => 'false',
                'current' => 'true',
            ],
        ], $tracker->getDifferences());
    }

    public function testRecordTrophyChangeFormatsContextWithFallbacksAndEncodesComplexValues(): void
    {
        $tracker = new GameRescanDifferenceTracker();

        $tracker->recordTrophyChange(
            'NPWR67890',
            7,
            '',
            '',
            'Rewards',
            ['coins' => 2],
            ['coins' => 5]
        );

        $this->assertSame([
            [
                'context' => 'Trophy "#7" (#7) in group "NPWR67890" (NPWR67890)',
                'field' => 'Rewards',
                'previous' => '{"coins":2}',
                'current' => '{"coins":5}',
            ],
        ], $tracker->getDifferences());
    }
}
