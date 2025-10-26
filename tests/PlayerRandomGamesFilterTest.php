<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerRandomGamesFilter.php';

final class PlayerRandomGamesFilterTest extends TestCase
{
    public function testFromArrayNormalizesSelectedPlatforms(): void
    {
        $filter = PlayerRandomGamesFilter::fromArray([
            'pc' => '1',
            'ps3' => '',
            'ps4' => '0',
            'ps5' => 'yes',
            'psvita' => null,
            'psvr' => 0,
            'psvr2' => 'on',
            'extra' => true,
        ]);

        $this->assertTrue($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PC));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS3));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS4));
        $this->assertTrue($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS5));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVITA));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVR));
        $this->assertTrue($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVR2));
        $this->assertFalse($filter->isPlatformSelected('extra'));
    }

    public function testFromArrayDefaultsToFalseForMissingPlatforms(): void
    {
        $filter = PlayerRandomGamesFilter::fromArray([]);

        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PC));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS3));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS4));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PS5));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVITA));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVR));
        $this->assertFalse($filter->isPlatformSelected(PlayerRandomGamesFilter::PLATFORM_PSVR2));
    }
}
