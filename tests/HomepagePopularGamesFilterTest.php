<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/HomepagePopularGamesFilter.php';

final class HomepagePopularGamesFilterTest extends TestCase
{
    public function testFromArrayDefaultsToAllPlatformsWithoutExclusiveFilter(): void
    {
        $filter = HomepagePopularGamesFilter::fromArray([]);

        $this->assertSame(HomepagePopularGamesFilter::PLATFORM_ALL, $filter->getPlatform());
        $this->assertFalse($filter->hasPlatformFilter());
        $this->assertFalse($filter->isExclusiveOnly());
        $this->assertSame([], $filter->getQueryParameters());
    }

    public function testFromArrayParsesPlatformAndExclusiveParameters(): void
    {
        $filter = HomepagePopularGamesFilter::fromArray([
            'platform' => 'ps5',
            'exclusive' => 'true',
        ]);

        $this->assertTrue($filter->isPlatformSelected(HomepagePopularGamesFilter::PLATFORM_PS5));
        $this->assertTrue($filter->isExclusiveOnly());
        $this->assertSame(
            [
                'platform' => 'ps5',
                'exclusive' => 'true',
            ],
            $filter->getQueryParameters()
        );
        $this->assertSame('PS5', $filter->getPlatformDatabaseValue());
    }

    public function testFromArrayRejectsUnknownPlatformValues(): void
    {
        $filter = HomepagePopularGamesFilter::fromArray([
            'platform' => 'dreamcast',
            'exclusive' => '1',
        ]);

        $this->assertFalse($filter->hasPlatformFilter());
        $this->assertTrue($filter->isExclusiveOnly());
        $this->assertSame(['exclusive' => 'true'], $filter->getQueryParameters());
    }
}
