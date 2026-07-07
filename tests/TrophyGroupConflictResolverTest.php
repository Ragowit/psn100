<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyGroupConflictResolver.php';

final class TrophyGroupConflictResolverTest extends TestCase
{
    private TrophyGroupConflictResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new TrophyGroupConflictResolver();
    }

    public function testParseNumericGroupIdAcceptsZeroPaddedDigits(): void
    {
        $this->assertSame(1, $this->resolver->parseNumericGroupId('001'));
        $this->assertSame(0, $this->resolver->parseNumericGroupId('000'));
        $this->assertSame(123, $this->resolver->parseNumericGroupId('123'));
    }

    public function testParseNumericGroupIdRejectsNonNumericIds(): void
    {
        $this->assertSame(null, $this->resolver->parseNumericGroupId('default'));
        $this->assertSame(null, $this->resolver->parseNumericGroupId('01a'));
    }

    public function testFormatGroupIdPadsToOriginalLength(): void
    {
        $this->assertSame('101', $this->resolver->formatGroupId(101, '001'));
        $this->assertSame('0101', $this->resolver->formatGroupId(101, '0100'));
    }

    public function testFormatGroupIdUsesMinimumLengthOfThree(): void
    {
        $this->assertSame('101', $this->resolver->formatGroupId(101, '1'));
    }

    public function testDeterminePreferredGroupOffsetUsesFirstMappedParentBlock(): void
    {
        $this->assertSame(200, $this->resolver->determinePreferredGroupOffset([
            '001' => '201',
            '002' => '305',
        ]));
    }

    public function testDeterminePreferredGroupOffsetReturnsNullWhenNoNumericMappingsExist(): void
    {
        $this->assertSame(null, $this->resolver->determinePreferredGroupOffset([
            '001' => 'default',
        ]));
        $this->assertSame(null, $this->resolver->determinePreferredGroupOffset([]));
    }

    public function testDetermineGroupOffsetReturnsNextBlockAfterHighestExistingGroup(): void
    {
        $this->assertSame(300, $this->resolver->determineGroupOffset([
            '001' => true,
            '205' => true,
        ]));
    }

    public function testDetermineGroupOffsetDefaultsToFirstBlockWhenNoNumericGroupsExist(): void
    {
        $this->assertSame(100, $this->resolver->determineGroupOffset([
            'default' => true,
        ]));
        $this->assertSame(100, $this->resolver->determineGroupOffset([]));
    }

    public function testFindMatchingParentGroupIdMatchesBySortedTrophyNames(): void
    {
        $childNames = [
            '002' => ['Alpha', 'Beta'],
        ];
        $parentNames = [
            '001' => ['Alpha', 'Beta'],
            '003' => ['Gamma'],
        ];

        $this->assertSame(
            '001',
            $this->resolver->findMatchingParentGroupId('002', $childNames, $parentNames, [])
        );
    }

    public function testFindMatchingParentGroupIdSkipsUsedAndSameIdGroups(): void
    {
        $childNames = [
            '002' => ['Alpha'],
        ];
        $parentNames = [
            '002' => ['Alpha'],
            '003' => ['Alpha'],
            '004' => ['Alpha'],
        ];

        $this->assertSame(
            '004',
            $this->resolver->findMatchingParentGroupId('002', $childNames, $parentNames, [
                '003' => true,
            ])
        );
    }

    public function testFindMatchingParentGroupIdReturnsNullWhenChildHasNoTrophies(): void
    {
        $this->assertSame(null, $this->resolver->findMatchingParentGroupId('002', [], ['001' => ['Alpha']], []));
        $this->assertSame(null, $this->resolver->findMatchingParentGroupId('002', ['002' => []], ['001' => ['Alpha']], []));
    }

    public function testAllocateNonConflictingGroupIdUsesPreferredOffsetWhenAvailable(): void
    {
        $result = $this->resolver->allocateNonConflictingGroupId(
            1,
            '001',
            [],
            200,
            300
        );

        $this->assertSame('201', $result['groupId']);
        $this->assertSame(200, $result['preferredOffset']);
        $this->assertSame(200, $result['groupOffset']);
    }

    public function testAllocateNonConflictingGroupIdFallsBackToGroupOffsetWhenPreferredIsNull(): void
    {
        $result = $this->resolver->allocateNonConflictingGroupId(
            1,
            '001',
            [],
            null,
            300
        );

        $this->assertSame('301', $result['groupId']);
        $this->assertSame(null, $result['preferredOffset']);
        $this->assertSame(300, $result['groupOffset']);
    }

    public function testAllocateNonConflictingGroupIdIncrementsOffsetUntilUnusedIdIsFound(): void
    {
        $result = $this->resolver->allocateNonConflictingGroupId(
            1,
            '001',
            ['201' => true],
            200,
            300
        );

        $this->assertSame('301', $result['groupId']);
        $this->assertSame(null, $result['preferredOffset']);
        $this->assertSame(300, $result['groupOffset']);
    }
}
