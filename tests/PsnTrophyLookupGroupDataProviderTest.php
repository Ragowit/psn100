<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyLookupGroupDataProvider.php';

final class PsnTrophyLookupGroupDataProviderTest extends TestCase
{
    public function testAdaptTrophyDataReturnsEmptyArrayWhenGroupsMissing(): void
    {
        $result = PsnTrophyLookupGroupDataProvider::adaptTrophyData([]);

        $this->assertSame([], $result);
    }

    public function testAdaptTrophyDataSkipsInvalidGroupEntries(): void
    {
        $result = PsnTrophyLookupGroupDataProvider::adaptTrophyData([
            'trophyGroups' => [
                'not-an-array',
                [
                    'trophyGroupId' => 'all',
                    'trophyGroupName' => 'Base Game',
                    'trophyGroupDetail' => 'Main campaign',
                    'trophyGroupIconUrl' => 'https://example.com/group.png',
                    'trophies' => [
                        [
                            'trophyId' => 7,
                            'trophyHidden' => true,
                            'trophyType' => 'gold',
                            'trophyName' => 'Complete Story',
                            'trophyDetail' => 'Finish the game',
                            'trophyIconUrl' => 'https://example.com/trophy.png',
                            'trophyProgressTargetValue' => 100,
                            'trophyRewardName' => 'Reward',
                            'trophyRewardImageUrl' => 'https://example.com/reward.png',
                        ],
                        'invalid-trophy',
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('all', $result[0]['group']->id());
        $this->assertSame('Base Game', $result[0]['group']->name());
        $this->assertSame('Main campaign', $result[0]['group']->detail());
        $this->assertSame('https://example.com/group.png', $result[0]['group']->iconUrl());

        $this->assertCount(1, $result[0]['trophies']);
        $trophy = $result[0]['trophies'][0];
        $this->assertSame(7, $trophy->id());
        $this->assertTrue($trophy->hidden());
        $this->assertSame('gold', $trophy->type()->value);
        $this->assertSame('Complete Story', $trophy->name());
        $this->assertSame('Finish the game', $trophy->detail());
        $this->assertSame('https://example.com/trophy.png', $trophy->iconUrl());
        $this->assertSame('100', $trophy->progressTargetValue());
        $this->assertSame('Reward', $trophy->rewardName());
        $this->assertSame('https://example.com/reward.png', $trophy->rewardImageUrl());
    }

    public function testAdaptTrophyDataUsesDefaultsForMissingFields(): void
    {
        $result = PsnTrophyLookupGroupDataProvider::adaptTrophyData([
            'trophyGroups' => [
                [
                    'trophies' => [
                        [],
                    ],
                ],
            ],
        ]);

        $this->assertSame('', $result[0]['group']->id());
        $this->assertSame('', $result[0]['group']->name());
        $this->assertSame(0, $result[0]['trophies'][0]->id());
        $this->assertFalse($result[0]['trophies'][0]->hidden());
        $this->assertSame('bronze', $result[0]['trophies'][0]->type()->value);
        $this->assertSame(null, $result[0]['trophies'][0]->rewardImageUrl());
    }
}
