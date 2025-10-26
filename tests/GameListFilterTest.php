<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameListFilter.php';

final class GameListFilterTest extends TestCase
{
    public function testFromArrayNormalizesParametersAndPlatformFilters(): void
    {
        $filter = GameListFilter::fromArray([
            'player' => '  Alice  ',
            'search' => '  God of War  ',
            'sort' => 'OWNERS',
            'page' => '2',
            'filter' => 'true',
            'ps4' => '1',
            'ps5' => 'false',
        ]);

        $this->assertSame('Alice', $filter->getPlayer());
        $this->assertTrue($filter->hasPlayer());
        $this->assertSame(GameListFilter::SORT_OWNERS, $filter->getSort());
        $this->assertTrue($filter->isSort(GameListFilter::SORT_OWNERS));
        $this->assertTrue($filter->hasExplicitSort());
        $this->assertSame('God of War', $filter->getSearch());
        $this->assertTrue($filter->hasSearch());
        $this->assertTrue($filter->shouldApplySearch());
        $this->assertSame(2, $filter->getPage());
        $this->assertSame(10, $filter->getOffset(10));
        $this->assertTrue($filter->shouldFilterUncompleted());
        $this->assertTrue($filter->shouldShowUncompletedOption());
        $this->assertTrue($filter->hasPlatformFilters());
        $this->assertTrue($filter->isPlatformSelected(GameListFilter::PLATFORM_PS4));
        $this->assertFalse($filter->isPlatformSelected(GameListFilter::PLATFORM_PS5));
        $this->assertFalse($filter->isPlatformSelected(GameListFilter::PLATFORM_PC));
        $this->assertSame([GameListFilter::PLATFORM_PS4], $filter->getSelectedPlatforms());
    }

    public function testFromArrayDefaultsSearchSortAndNormalizesPage(): void
    {
        $filter = GameListFilter::fromArray([
            'search' => '  Kratos  ',
            'page' => '0',
            'filter' => 'false',
        ]);

        $this->assertSame(GameListFilter::SORT_SEARCH, $filter->getSort());
        $this->assertFalse($filter->hasExplicitSort());
        $this->assertSame('Kratos', $filter->getSearch());
        $this->assertTrue($filter->hasSearch());
        $this->assertTrue($filter->shouldApplySearch());
        $this->assertSame(1, $filter->getPage());
        $this->assertFalse($filter->shouldFilterUncompleted());
    }

    public function testGetQueryParametersForPaginationReturnsNormalizedSubset(): void
    {
        $filter = GameListFilter::fromArray([
            'player' => '   ',
            'sort' => 'search',
            'search' => '   ',
            'filter' => 'true',
            'ps3' => 'true',
            'ps4' => 'false',
            'page' => '5',
        ]);

        $this->assertTrue($filter->hasPlatformFilters());
        $this->assertTrue($filter->isSort(GameListFilter::SORT_SEARCH));
        $this->assertTrue($filter->shouldApplySearch());

        $this->assertSame(
            [
                'sort' => 'search',
                'filter' => 'true',
                'ps3' => 'true',
            ],
            $filter->getQueryParametersForPagination()
        );
    }
}
