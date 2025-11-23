<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisorFilter.php';
require_once __DIR__ . '/TestCase.php';

final class PlayerAdvisorFilterTest extends TestCase
{
    public function testFromArrayUsesDefaultValuesWhenParametersMissing(): void
    {
        $filter = PlayerAdvisorFilter::fromArray([]);

        $this->assertSame(1, $filter->getPage());
        $this->assertSame(PlayerAdvisorFilter::SORT_RARITY, $filter->getSort());
        $this->assertFalse($filter->hasPlatformFilters());
        $this->assertSame([], $filter->getPlatforms());
    }

    public function testFromArrayParsesPageAndSupportedPlatforms(): void
    {
        $filter = PlayerAdvisorFilter::fromArray([
            'page' => '3',
            'ps3' => '1',
            'ps4' => '',
            'ps5' => 'true',
            'pc' => 'yes',
            'unknown' => 'true',
        ]);

        $this->assertSame(3, $filter->getPage());
        $this->assertTrue($filter->hasPlatformFilters());
        $this->assertSame(['pc', 'ps3', 'ps5'], $filter->getPlatforms());
        $this->assertTrue($filter->isPlatformSelected('pc'));
        $this->assertTrue($filter->isPlatformSelected('ps3'));
        $this->assertTrue($filter->isPlatformSelected('ps5'));
        $this->assertFalse($filter->isPlatformSelected('ps4'));
        $this->assertFalse($filter->isPlatformSelected('unknown'));
    }

    public function testFromArrayParsesSortWhenValid(): void
    {
        $filter = PlayerAdvisorFilter::fromArray(['sort' => PlayerAdvisorFilter::SORT_IN_GAME_RARITY]);

        $this->assertSame(PlayerAdvisorFilter::SORT_IN_GAME_RARITY, $filter->getSort());
    }

    public function testFromArrayFallsBackToDefaultSortWhenUnknown(): void
    {
        $filter = PlayerAdvisorFilter::fromArray(['sort' => 'unknown']);

        $this->assertSame(PlayerAdvisorFilter::SORT_RARITY, $filter->getSort());
    }

    public function testPageIsNeverBelowOne(): void
    {
        $filter = PlayerAdvisorFilter::fromArray(['page' => '0']);

        $this->assertSame(1, $filter->getPage());
    }

    public function testGetOffsetCalculatesOffsetFromPage(): void
    {
        $filter = PlayerAdvisorFilter::fromArray(['page' => 4]);

        $this->assertSame(60, $filter->getOffset(20));
    }

    public function testGetFilterParametersIncludesSortAndPlatforms(): void
    {
        $filter = PlayerAdvisorFilter::fromArray([
            'psvr' => '1',
            'psvita' => 'yes',
            'sort' => PlayerAdvisorFilter::SORT_IN_GAME_RARITY,
        ]);

        $this->assertSame([
            'psvita' => 'true',
            'psvr' => 'true',
            'sort' => PlayerAdvisorFilter::SORT_IN_GAME_RARITY,
        ], $filter->getFilterParameters());
    }
}
