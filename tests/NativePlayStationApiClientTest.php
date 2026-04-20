<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/NativePlayStationApiClient.php';

final class NativePlayStationApiClientTest extends TestCase
{
    public function testOauthClientSecretMatchesCurrentNativeClientSecret(): void
    {
        $reflection = new ReflectionClass(NativePlayStationApiClient::class);
        $clientSecret = $reflection->getConstant('OAUTH_CLIENT_SECRET');

        $this->assertSame('ucPjka5tntB2KqsP', $clientSecret);
    }
}
