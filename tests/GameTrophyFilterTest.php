<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameTrophyFilter.php';
require_once __DIR__ . '/../wwwroot/classes/Game/GameTrophyGroupPlayer.php';

final class GameTrophyFilterTest extends TestCase
{
    public function testFromQueryParametersEnablesUnearnedFilterWhenAllowed(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters(['unearned' => '1'], true);

        $this->assertTrue($filter->shouldShowUnearnedOnly());
    }

    public function testFromQueryParametersTreatsCommonFalsyValuesAsDisabled(): void
    {
        $values = [null, false, 0, '0', 'false', 'off', 'no', ''];

        foreach ($values as $value) {
            $filter = GameTrophyFilter::fromQueryParameters(['unearned' => $value], true);

            $this->assertFalse(
                $filter->shouldShowUnearnedOnly(),
                sprintf('Expected "%s" to be treated as falsy.', var_export($value, true))
            );
        }
    }

    public function testFromQueryParametersIgnoresUserInputWhenUnearnedFilterDisallowed(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters(['unearned' => true], false);

        $this->assertFalse($filter->shouldShowUnearnedOnly());
    }

    public function testShouldDisplayGroupRequiresIncompleteProgressWhenUnearnedFilterEnabled(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters(['unearned' => true], true);

        $this->assertTrue($filter->shouldDisplayGroup(null));
        $this->assertTrue($filter->shouldDisplayGroup($this->createGroupPlayer()));
        $this->assertTrue($filter->shouldDisplayGroup($this->createGroupPlayer(['progress' => 50])));
        $this->assertTrue($filter->shouldDisplayGroup($this->createGroupPlayer(['progress' => '75'])));
        $this->assertFalse($filter->shouldDisplayGroup($this->createGroupPlayer(['progress' => 100])));
        $this->assertFalse($filter->shouldDisplayGroup($this->createGroupPlayer(['progress' => '100'])));
    }

    public function testShouldDisplayGroupAlwaysReturnsTrueWhenUnearnedFilterDisabled(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters([], false);

        $this->assertTrue($filter->shouldDisplayGroup(null));
        $this->assertTrue($filter->shouldDisplayGroup($this->createGroupPlayer(['progress' => 0])));
        $this->assertTrue($filter->shouldDisplayGroup($this->createGroupPlayer(['progress' => 100])));
    }

    public function testShouldDisplayTrophyRequiresUnearnedFlag(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters(['unearned' => true], true);

        $this->assertTrue($filter->shouldDisplayTrophy(['earned' => 0]));
        $this->assertTrue($filter->shouldDisplayTrophy(['earned' => null]));
        $this->assertTrue($filter->shouldDisplayTrophy([]));
        $this->assertFalse($filter->shouldDisplayTrophy(['earned' => 1]));
        $this->assertFalse($filter->shouldDisplayTrophy(['earned' => '1']));
    }

    public function testShouldDisplayTrophyAlwaysReturnsTrueWhenUnearnedFilterDisabled(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters([], false);

        $this->assertTrue($filter->shouldDisplayTrophy(['earned' => 1]));
        $this->assertTrue($filter->shouldDisplayTrophy(['earned' => 0]));
        $this->assertTrue($filter->shouldDisplayTrophy([]));
    }

    public function testShouldDisplayTrophySupportsViewModelInstances(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters(['unearned' => true], true);

        $utility = new Utility();
        $pendingTrophy = GameTrophyRow::fromArray(
            [
                'id' => 1,
                'order_id' => 1,
                'type' => 'bronze',
                'name' => 'Pending Trophy',
                'detail' => 'Detail',
                'icon_url' => 'icon.png',
                'rarity_percent' => 10,
                'status' => 0,
            ],
            $utility,
            false
        );

        $earnedTrophy = GameTrophyRow::fromArray(
            [
                'id' => 2,
                'order_id' => 2,
                'type' => 'silver',
                'name' => 'Earned Trophy',
                'detail' => 'Detail',
                'icon_url' => 'icon.png',
                'rarity_percent' => 5,
                'status' => 0,
                'earned' => 1,
            ],
            $utility,
            false
        );

        $this->assertTrue($filter->shouldDisplayTrophy($pendingTrophy));
        $this->assertFalse($filter->shouldDisplayTrophy($earnedTrophy));
    }

    public function testFromQueryParametersTreatsUnexpectedTypesAsTruthy(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters(['unearned' => ['unexpected']], true);

        $this->assertTrue($filter->shouldShowUnearnedOnly());
    }

    private function createGroupPlayer(array $overrides = []): GameTrophyGroupPlayer
    {
        $defaults = [
            'np_communication_id' => 'NPWRTEST',
            'group_id' => 'default',
            'account_id' => 1,
            'bronze' => 0,
            'silver' => 0,
            'gold' => 0,
            'platinum' => 0,
            'progress' => 0,
        ];

        return GameTrophyGroupPlayer::fromArray($overrides + $defaults);
    }
}
