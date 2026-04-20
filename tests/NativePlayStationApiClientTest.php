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

    public function testMergeAccountLookupPayloadWithTrophySummaryUsesNestedProfileWhenPresent(): void
    {
        $client = new NativePlayStationApiClient();
        $method = new ReflectionMethod(NativePlayStationApiClient::class, 'mergeAccountLookupPayloadWithTrophySummary');
        $method->setAccessible(true);

        $payload = [
            'profile' => [
                'accountId' => '123',
                'onlineId' => 'PlayerOne',
            ],
        ];
        $trophySummaryPayload = [
            'level' => 321,
            'progress' => 99,
            'earnedTrophies' => [
                'platinum' => 1,
                'gold' => 2,
                'silver' => 3,
                'bronze' => 4,
            ],
        ];

        $result = $method->invoke($client, $payload, $trophySummaryPayload);

        $this->assertSame($trophySummaryPayload, $result['profile']['trophySummary']);
        $this->assertSame('PlayerOne', $result['profile']['onlineId']);
    }

    public function testMapUserSearchResultsPrefersCurrentOnlineIdWhenAvailable(): void
    {
        $client = new NativePlayStationApiClient();
        $method = new ReflectionMethod(NativePlayStationApiClient::class, 'mapUserSearchResults');
        $method->setAccessible(true);

        $payload = [
            'domainResponses' => [
                [
                    'results' => [
                        [
                            'socialMetadata' => [
                                'accountId' => '300',
                                'onlineId' => 'LegacyName',
                                'currentOnlineId' => 'CurrentName',
                                'country' => 'US',
                                'aboutMe' => 'Bio',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $results = iterator_to_array($method->invoke($client, $payload), false);

        $this->assertSame(
            [[
                'onlineId' => 'CurrentName',
                'country' => 'US',
                'aboutMe' => 'Bio',
            ]],
            $results
        );
    }
}
