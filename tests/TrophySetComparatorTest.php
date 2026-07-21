<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophySetComparator.php';

final class TrophySetComparatorTest extends TestCase
{
    private TrophySetComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new TrophySetComparator();
    }

    public function testCompareReturnsNoMatchWhenCountsDiffer(): void
    {
        $left = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy A', 'detail' => 'Detail A'],
        ];
        $right = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy A', 'detail' => 'Detail A'],
            ['group_id' => 'default', 'order_id' => 1, 'name' => 'Trophy B', 'detail' => 'Detail B'],
        ];

        $this->assertSame(
            ['matches' => false, 'orderMatches' => false, 'nameMatches' => false],
            $this->comparator->compare($left, $right),
        );
    }

    public function testCompareReturnsMatchForEmptySets(): void
    {
        $this->assertSame(
            ['matches' => true, 'orderMatches' => true, 'nameMatches' => true],
            $this->comparator->compare([], []),
        );
    }

    public function testCompareReturnsOrderMatchForIdenticalSets(): void
    {
        $trophies = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy A', 'detail' => 'Detail A'],
            ['group_id' => 'default', 'order_id' => 1, 'name' => 'Trophy B', 'detail' => 'Detail B'],
        ];

        $this->assertSame(
            ['matches' => true, 'orderMatches' => true, 'nameMatches' => true],
            $this->comparator->compare($trophies, $trophies),
        );
    }

    public function testCompareIsCaseInsensitive(): void
    {
        $left = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy A', 'detail' => 'Detail A'],
        ];
        $right = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'trophy a', 'detail' => 'detail a'],
        ];

        $this->assertSame(
            ['matches' => true, 'orderMatches' => true, 'nameMatches' => true],
            $this->comparator->compare($left, $right),
        );
    }

    public function testCompareReturnsNoMatchWhenNameDetailMultisetDiffers(): void
    {
        $left = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy A', 'detail' => 'Alpha'],
            ['group_id' => 'default', 'order_id' => 1, 'name' => 'Trophy B', 'detail' => 'Beta'],
        ];
        $right = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy A', 'detail' => 'Beta'],
            ['group_id' => 'default', 'order_id' => 1, 'name' => 'Trophy B', 'detail' => 'Alpha'],
        ];

        $this->assertSame(
            ['matches' => false, 'orderMatches' => false, 'nameMatches' => false],
            $this->comparator->compare($left, $right),
        );
    }

    public function testCompareReturnsNameMatchWhenOrderDiffersButNamesAreUnique(): void
    {
        $left = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy A', 'detail' => 'Alpha'],
            ['group_id' => 'default', 'order_id' => 1, 'name' => 'Trophy B', 'detail' => 'Beta'],
        ];
        $right = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Trophy B', 'detail' => 'Beta'],
            ['group_id' => 'default', 'order_id' => 1, 'name' => 'Trophy A', 'detail' => 'Alpha'],
        ];

        $this->assertSame(
            ['matches' => true, 'orderMatches' => false, 'nameMatches' => true],
            $this->comparator->compare($left, $right),
        );
    }

    public function testCompareReturnsAmbiguousWhenDuplicateNamesPreventNameMatch(): void
    {
        $left = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Hidden Trophy', 'detail' => 'Secret'],
            ['group_id' => 'default', 'order_id' => 1, 'name' => 'Hidden Trophy', 'detail' => 'Secret'],
        ];
        $right = [
            ['group_id' => 'default', 'order_id' => 0, 'name' => 'Hidden Trophy', 'detail' => 'Secret'],
            ['group_id' => 'dlc', 'order_id' => 0, 'name' => 'Hidden Trophy', 'detail' => 'Secret'],
        ];

        $this->assertSame(
            ['matches' => true, 'orderMatches' => false, 'nameMatches' => false],
            $this->comparator->compare($left, $right),
        );
    }

    public function testSelectMergeMethodPrefersOrder(): void
    {
        $this->assertSame(TrophyMergeMethod::Order, $this->comparator->selectMergeMethod([
            'matches' => true,
            'orderMatches' => true,
            'nameMatches' => true,
        ]));
    }

    public function testSelectMergeMethodFallsBackToName(): void
    {
        $this->assertSame(TrophyMergeMethod::Name, $this->comparator->selectMergeMethod([
            'matches' => true,
            'orderMatches' => false,
            'nameMatches' => true,
        ]));
    }

    public function testSelectMergeMethodReturnsNullWhenAmbiguous(): void
    {
        $this->assertSame(null, $this->comparator->selectMergeMethod([
            'matches' => true,
            'orderMatches' => false,
            'nameMatches' => false,
        ]));
    }
}
