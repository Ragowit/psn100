<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnTrophyGroupAssembler.php';

final class PsnTrophyGroupAssemblerTest extends TestCase
{
    private PsnTrophyGroupAssembler $assembler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assembler = new PsnTrophyGroupAssembler();
    }

    public function testGroupTrophiesByGroupIdGroupsFlatTrophies(): void
    {
        $groups = $this->assembler->groupTrophiesByGroupId([
            [
                'trophyGroupId' => 'default',
                'trophyGroupName' => 'Base',
                'trophyId' => 1,
                'trophyName' => 'Bronze',
            ],
            [
                'trophyGroupId' => 'default',
                'trophyId' => 2,
                'trophyName' => 'Silver',
            ],
            [
                'trophyGroupId' => '001',
                'trophyGroupName' => 'DLC',
                'trophyGroupDetail' => 'Extra',
                'trophyGroupIconUrl' => 'https://example.com/dlc.png',
                'trophyId' => 3,
            ],
            [
                'trophyId' => 4,
            ],
            'not-an-array',
        ]);

        $this->assertCount(2, $groups);
        $this->assertSame('default', $groups[0]['trophyGroupId']);
        $this->assertSame('Base', $groups[0]['trophyGroupName']);
        $this->assertSame([1, 2], array_column($groups[0]['trophies'], 'trophyId'));
        $this->assertSame('001', $groups[1]['trophyGroupId']);
        $this->assertSame('DLC', $groups[1]['trophyGroupName']);
        $this->assertSame('Extra', $groups[1]['trophyGroupDetail']);
        $this->assertSame('https://example.com/dlc.png', $groups[1]['trophyGroupIconUrl']);
        $this->assertSame([3], array_column($groups[1]['trophies'], 'trophyId'));
    }

    public function testGroupTrophiesByGroupIdReturnsEmptyForNonArray(): void
    {
        $this->assertSame([], $this->assembler->groupTrophiesByGroupId(null));
        $this->assertSame([], $this->assembler->groupTrophiesByGroupId('oops'));
    }

    public function testGroupNestedTrophiesFromGroupsPreservesNestedPayload(): void
    {
        $groups = $this->assembler->groupNestedTrophiesFromGroups([
            [
                'trophyGroupId' => 'all',
                'trophyGroupName' => 'All',
                'trophyGroupDetail' => 'Detail',
                'trophyGroupIconUrl' => 'https://example.com/all.png',
                'trophies' => [
                    ['trophyId' => 10, 'trophyName' => 'Nested'],
                ],
            ],
            [
                'trophyGroupId' => 'empty',
                'trophies' => null,
            ],
            [
                'trophyGroupName' => 'Missing id',
            ],
            'not-an-array',
        ]);

        $this->assertCount(2, $groups);
        $this->assertSame('all', $groups[0]['trophyGroupId']);
        $this->assertSame('All', $groups[0]['trophyGroupName']);
        $this->assertSame('Detail', $groups[0]['trophyGroupDetail']);
        $this->assertSame('https://example.com/all.png', $groups[0]['trophyGroupIconUrl']);
        $this->assertSame('Nested', $groups[0]['trophies'][0]['trophyName']);
        $this->assertSame('empty', $groups[1]['trophyGroupId']);
        $this->assertSame([], $groups[1]['trophies']);
    }

    public function testBuildTrophyGroupsPrefersEndpointMetadata(): void
    {
        $groupedTrophies = [
            [
                'trophyGroupId' => 'default',
                'trophyGroupName' => 'From trophies',
                'trophyGroupDetail' => '',
                'trophyGroupIconUrl' => '',
                'trophies' => [
                    ['trophyId' => 1, 'trophyName' => 'Bronze Trophy'],
                ],
            ],
        ];

        $groups = $this->assembler->buildTrophyGroups(
            [
                [
                    'trophyGroupId' => 'default',
                    'trophyGroupName' => 'Base Group Name',
                    'trophyGroupDetail' => 'Base Group Detail',
                    'trophyGroupIconUrl' => 'https://example.com/group-icon.png',
                ],
            ],
            $groupedTrophies,
        );

        $this->assertCount(1, $groups);
        $this->assertSame('Base Group Name', $groups[0]['trophyGroupName']);
        $this->assertSame('Base Group Detail', $groups[0]['trophyGroupDetail']);
        $this->assertSame('https://example.com/group-icon.png', $groups[0]['trophyGroupIconUrl']);
        $this->assertSame('Bronze Trophy', $groups[0]['trophies'][0]['trophyName']);
    }

    public function testBuildTrophyGroupsFallsBackWhenEndpointGroupsMissing(): void
    {
        $groupedTrophies = [
            [
                'trophyGroupId' => 'default',
                'trophyGroupName' => 'Fallback',
                'trophyGroupDetail' => '',
                'trophyGroupIconUrl' => '',
                'trophies' => [['trophyId' => 7]],
            ],
        ];

        $this->assertSame($groupedTrophies, $this->assembler->buildTrophyGroups(null, $groupedTrophies));
        $this->assertSame($groupedTrophies, $this->assembler->buildTrophyGroups([], $groupedTrophies));
    }

    public function testAssemblePrefersFlatTrophiesOverNestedGroups(): void
    {
        $assembled = $this->assembler->assemble(
            [
                [
                    'trophyGroupId' => 'default',
                    'trophyId' => 1,
                    'trophyName' => 'Flat',
                ],
            ],
            [
                [
                    'trophyGroupId' => 'default',
                    'trophies' => [
                        ['trophyId' => 99, 'trophyName' => 'Nested'],
                    ],
                ],
            ],
            [
                [
                    'trophyGroupId' => 'default',
                    'trophyGroupName' => 'Endpoint Name',
                    'trophyGroupDetail' => '',
                    'trophyGroupIconUrl' => '',
                ],
            ],
        );

        $this->assertSame('Endpoint Name', $assembled[0]['trophyGroupName']);
        $this->assertSame('Flat', $assembled[0]['trophies'][0]['trophyName']);
        $this->assertCount(1, $assembled[0]['trophies']);
    }

    public function testAssembleUsesNestedGroupsWhenFlatTrophiesMissing(): void
    {
        $assembled = $this->assembler->assemble(
            null,
            [
                [
                    'trophyGroupId' => 'all',
                    'trophyGroupName' => 'All',
                    'trophies' => [
                        ['trophyId' => 5, 'trophyName' => 'Nested Only'],
                    ],
                ],
            ],
            null,
        );

        $this->assertSame('all', $assembled[0]['trophyGroupId']);
        $this->assertSame('Nested Only', $assembled[0]['trophies'][0]['trophyName']);
    }

    public function testAssembleReturnsEmptyWhenNoTrophyDataPresent(): void
    {
        $this->assertSame([], $this->assembler->assemble(null, null, null));
    }
}
