<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameHistoryChangeFilter.php';

final class GameHistoryChangeFilterTest extends TestCase
{
    public function testFilterReturnsEmptyArrayForEmptyInput(): void
    {
        $filter = new GameHistoryChangeFilter();

        $this->assertSame([], $filter->filter([]));
    }

    public function testFilterOmitsUnchangedRowsAndHighlightsDifferences(): void
    {
        $filter = new GameHistoryChangeFilter();

        $entries = [
            [
                'historyId' => 4,
                'discoveredAt' => new DateTimeImmutable('2024-04-01 00:00:00'),
                'title' => ['detail' => null, 'icon_url' => null, 'set_version' => '01.10'],
                'groups' => [
                    ['group_id' => 'default', 'name' => 'Base', 'detail' => 'Base detail', 'icon_url' => 'group-a.png'],
                    ['group_id' => '001', 'name' => 'Expansion', 'detail' => 'Expansion detail', 'icon_url' => '.png'],
                ],
                'trophies' => [
                    ['group_id' => 'default', 'order_id' => 1, 'name' => 'First Trophy', 'detail' => 'Earn it', 'icon_url' => 'trophy-a.png', 'progress_target_value' => null, 'is_unobtainable' => false],
                    ['group_id' => '001', 'order_id' => 5, 'name' => 'Challenger', 'detail' => 'Reach level 5', 'icon_url' => '.png', 'progress_target_value' => 100, 'is_unobtainable' => true],
                ],
            ],
            [
                'historyId' => 3,
                'discoveredAt' => new DateTimeImmutable('2024-03-05 00:00:00'),
                'title' => ['detail' => null, 'icon_url' => null, 'set_version' => '01.10'],
                'groups' => [
                    ['group_id' => 'default', 'name' => 'Base', 'detail' => 'Base detail', 'icon_url' => 'group-a.png'],
                    ['group_id' => '001', 'name' => 'Expansion', 'detail' => 'Expansion detail', 'icon_url' => '.png'],
                ],
                'trophies' => [
                    ['group_id' => 'default', 'order_id' => 1, 'name' => 'First Trophy', 'detail' => 'Earn it', 'icon_url' => 'trophy-a.png', 'progress_target_value' => null, 'is_unobtainable' => false],
                    ['group_id' => '001', 'order_id' => 5, 'name' => 'Challenger', 'detail' => 'Reach level 5', 'icon_url' => '.png', 'progress_target_value' => 100, 'is_unobtainable' => true],
                ],
            ],
            [
                'historyId' => 2,
                'discoveredAt' => new DateTimeImmutable('2024-02-01 00:00:00'),
                'title' => ['detail' => null, 'icon_url' => null, 'set_version' => '01.05'],
                'groups' => [
                    ['group_id' => 'default', 'name' => 'Base', 'detail' => 'Base detail', 'icon_url' => 'group-a.png'],
                    ['group_id' => '001', 'name' => 'Expansion', 'detail' => 'Expansion detail', 'icon_url' => '.png'],
                ],
                'trophies' => [
                    ['group_id' => 'default', 'order_id' => 1, 'name' => 'First Trophy', 'detail' => 'Earn it', 'icon_url' => 'trophy-a.png', 'progress_target_value' => null, 'is_unobtainable' => false],
                    ['group_id' => '001', 'order_id' => 5, 'name' => 'Challenger', 'detail' => 'Reach level 5', 'icon_url' => '.png', 'progress_target_value' => 50, 'is_unobtainable' => true],
                ],
            ],
            [
                'historyId' => 1,
                'discoveredAt' => new DateTimeImmutable('2024-01-01 00:00:00'),
                'title' => ['detail' => 'Initial detail', 'icon_url' => 'icon-a.png', 'set_version' => '01.00'],
                'groups' => [
                    ['group_id' => 'default', 'name' => 'Base', 'detail' => 'Base detail', 'icon_url' => 'group-a.png'],
                ],
                'trophies' => [
                    ['group_id' => 'default', 'order_id' => 1, 'name' => 'First Trophy', 'detail' => 'Earn it', 'icon_url' => 'trophy-a.png', 'progress_target_value' => null, 'is_unobtainable' => false],
                ],
            ],
        ];

        $filtered = $filter->filter($entries);

        $this->assertCount(3, $filtered);
        $this->assertSame([3, 2, 1], array_column($filtered, 'historyId'));

        $latestEntry = $filtered[0];
        $this->assertTrue($latestEntry['hasTitleChanges']);
        $this->assertTrue($latestEntry['titleHighlights']['set_version']);
        $this->assertFalse($latestEntry['titleHighlights']['detail']);
        $this->assertSame([
            'set_version' => [
                'previous' => '01.05',
                'current' => '01.10',
            ],
        ], $latestEntry['titleFieldDiffs']);
        $this->assertSame([], $latestEntry['groups']);
        $this->assertCount(1, $latestEntry['trophies']);
        $this->assertTrue($latestEntry['trophies'][0]['changedFields']['progress_target_value']);

        $midEntry = $filtered[1];
        $this->assertTrue($midEntry['hasTitleChanges']);
        $this->assertTrue($midEntry['titleHighlights']['set_version']);
        $this->assertSame([
            'set_version' => [
                'previous' => '01.00',
                'current' => '01.05',
            ],
        ], $midEntry['titleFieldDiffs']);
        $this->assertCount(1, $midEntry['groups']);
        $this->assertTrue($midEntry['groups'][0]['isNewRow']);
        $this->assertCount(1, $midEntry['trophies']);
        $this->assertTrue($midEntry['trophies'][0]['isNewRow']);
        $this->assertTrue($midEntry['trophies'][0]['is_unobtainable']);

        $earliestEntry = $filtered[2];
        $this->assertTrue($earliestEntry['hasTitleChanges']);
        $this->assertTrue($earliestEntry['titleHighlights']['icon_url']);
        $this->assertTrue($earliestEntry['titleHighlights']['detail']);
        $this->assertSame([
            'detail' => [
                'previous' => null,
                'current' => 'Initial detail',
            ],
            'icon_url' => [
                'previous' => null,
                'current' => 'icon-a.png',
            ],
            'set_version' => [
                'previous' => null,
                'current' => '01.00',
            ],
        ], $earliestEntry['titleFieldDiffs']);
        $this->assertCount(1, $earliestEntry['groups']);
        $this->assertTrue($earliestEntry['groups'][0]['isNewRow']);
        $this->assertCount(1, $earliestEntry['trophies']);
        $this->assertTrue($earliestEntry['trophies'][0]['isNewRow']);
        $this->assertFalse($earliestEntry['trophies'][0]['is_unobtainable']);
    }
}
