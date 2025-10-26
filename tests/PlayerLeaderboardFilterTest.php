<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerLeaderboardFilter.php';

final class PlayerLeaderboardFilterTest extends TestCase
{
    public function testConstructorNormalizesInputs(): void
    {
        $filter = new PlayerLeaderboardFilter('  US  ', "  cat-01  ", 0);

        $this->assertSame('US', $filter->getCountry());
        $this->assertSame('cat-01', $filter->getAvatar());
        $this->assertSame(1, $filter->getPage());
        $this->assertTrue($filter->hasCountry());
        $this->assertTrue($filter->hasAvatar());
    }

    public function testConstructorTreatsEmptyStringsAsNull(): void
    {
        $filter = new PlayerLeaderboardFilter('   ', "", 2);

        $this->assertSame(null, $filter->getCountry());
        $this->assertSame(null, $filter->getAvatar());
        $this->assertFalse($filter->hasCountry());
        $this->assertFalse($filter->hasAvatar());
    }

    public function testFromArrayCastsValuesAndHandlesInvalidPage(): void
    {
        $filter = PlayerLeaderboardFilter::fromArray([
            'country' => '  CA ',
            'avatar' => '  fox  ',
            'page' => '3',
        ]);

        $this->assertSame('CA', $filter->getCountry());
        $this->assertSame('fox', $filter->getAvatar());
        $this->assertSame(3, $filter->getPage());

        $invalidPageFilter = PlayerLeaderboardFilter::fromArray([
            'country' => 'FR',
            'page' => 'invalid',
        ]);

        $this->assertSame(1, $invalidPageFilter->getPage());
    }

    public function testGetFilterParametersOnlyIncludesDefinedFilters(): void
    {
        $filter = new PlayerLeaderboardFilter(null, 'ghost', 4);

        $this->assertSame(['avatar' => 'ghost'], $filter->getFilterParameters());
    }

    public function testOffsetAndQueryParameterHelpers(): void
    {
        $filter = new PlayerLeaderboardFilter('JP', null, 5);

        $this->assertSame(40, $filter->getOffset(10));
        $this->assertSame(
            ['country' => 'JP', 'page' => 5],
            $filter->toQueryParameters()
        );

        $this->assertSame(
            ['country' => 'JP', 'page' => 1],
            $filter->withPage(0)
        );
    }
}
