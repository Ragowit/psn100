<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PsnApi/autoload.php';

use PsnApi\HttpClient;
use PsnApi\UsersApi;

final class UsersApiTest extends TestCase
{
    public function testExtractCountryPrefersSocialMetadata(): void
    {
        $player = (object) [
            'country' => 'US',
            'socialMetadata' => (object) [
                'personalDetail' => (object) [
                    'countryAlphaTwo' => 'SE',
                ],
            ],
        ];

        $usersApi = new UsersApi(new HttpClient(null));
        $reflection = new ReflectionMethod(UsersApi::class, 'extractCountry');
        $reflection->setAccessible(true);

        $country = $reflection->invoke($usersApi, $player);

        $this->assertSame('SE', $country);
    }
}
