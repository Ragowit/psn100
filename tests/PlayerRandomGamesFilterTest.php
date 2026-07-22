<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Platform.php';
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

        $this->assertTrue($filter->isPlatformSelected(Platform::Pc->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::Ps3->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::Ps4->value));
        $this->assertTrue($filter->isPlatformSelected(Platform::Ps5->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::PsVita->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::PsVr->value));
        $this->assertTrue($filter->isPlatformSelected(Platform::PsVr2->value));
        $this->assertFalse($filter->isPlatformSelected('extra'));
    }

    public function testFromArrayDefaultsToFalseForMissingPlatforms(): void
    {
        $filter = PlayerRandomGamesFilter::fromArray([]);

        $this->assertFalse($filter->isPlatformSelected(Platform::Pc->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::Ps3->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::Ps4->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::Ps5->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::PsVita->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::PsVr->value));
        $this->assertFalse($filter->isPlatformSelected(Platform::PsVr2->value));
    }
}
