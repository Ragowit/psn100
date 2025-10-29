<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameTrophyFilter.php';

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
        $this->assertTrue($filter->shouldDisplayGroup([]));
        $this->assertTrue($filter->shouldDisplayGroup(['progress' => 50]));
        $this->assertTrue($filter->shouldDisplayGroup(['progress' => '75']));
        $this->assertFalse($filter->shouldDisplayGroup(['progress' => 100]));
        $this->assertFalse($filter->shouldDisplayGroup(['progress' => '100']));
    }

    public function testShouldDisplayGroupAlwaysReturnsTrueWhenUnearnedFilterDisabled(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters([], false);

        $this->assertTrue($filter->shouldDisplayGroup(null));
        $this->assertTrue($filter->shouldDisplayGroup(['progress' => 0]));
        $this->assertTrue($filter->shouldDisplayGroup(['progress' => 100]));
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

    public function testFromQueryParametersTreatsUnexpectedTypesAsTruthy(): void
    {
        $filter = GameTrophyFilter::fromQueryParameters(['unearned' => ['unexpected']], true);

        $this->assertTrue($filter->shouldShowUnearnedOnly());
    }
}
