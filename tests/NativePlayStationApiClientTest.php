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

    public function testExtractUserSearchCandidatesFindsNestedAccountEntries(): void
    {
        $client = new NativePlayStationApiClient();
        $method = new ReflectionMethod(NativePlayStationApiClient::class, 'extractUserSearchCandidates');
        $method->setAccessible(true);

        $payload = [
            'domainResponses' => [
                [
                    'domain' => 'SocialAllAccounts',
                    'results' => [
                        [
                            'socialMetadata' => [
                                'accountId' => '1882371903386905898',
                                'onlineId' => 'Ragowit',
                                'country' => 'PL',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $method->invoke($client, $payload);

        $this->assertSame(
            [[
                'accountId' => '1882371903386905898',
                'onlineId' => 'Ragowit',
                'currentOnlineId' => null,
                'country' => 'PL',
                'aboutMe' => null,
            ]],
            $result
        );
    }

    public function testExtractUserSearchCandidatesAcceptsCurrentOnlineIdFallback(): void
    {
        $client = new NativePlayStationApiClient();
        $method = new ReflectionMethod(NativePlayStationApiClient::class, 'extractUserSearchCandidates');
        $method->setAccessible(true);

        $payload = [
            'accountId' => '100',
            'currentOnlineId' => 'CurrentName',
            'aboutMe' => 'Bio',
        ];

        $result = $method->invoke($client, $payload);

        $this->assertSame(
            [[
                'accountId' => '100',
                'onlineId' => 'CurrentName',
                'currentOnlineId' => 'CurrentName',
                'country' => null,
                'aboutMe' => 'Bio',
            ]],
            $result
        );
    }

    public function testExtractUserSearchCandidatesPreservesBothOnlineIdFields(): void
    {
        $client = new NativePlayStationApiClient();
        $method = new ReflectionMethod(NativePlayStationApiClient::class, 'extractUserSearchCandidates');
        $method->setAccessible(true);

        $payload = [
            'accountId' => '200',
            'onlineId' => 'LegacyName',
            'currentOnlineId' => 'CurrentName',
        ];

        $result = $method->invoke($client, $payload);

        $this->assertSame(
            [[
                'accountId' => '200',
                'onlineId' => 'LegacyName',
                'currentOnlineId' => 'CurrentName',
                'country' => null,
                'aboutMe' => null,
            ]],
            $result
        );
    }

}
