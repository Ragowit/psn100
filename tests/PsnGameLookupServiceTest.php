<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnGameLookupService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnGameLookupRequestHandler.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';

if (!class_exists('Tustin\\Haste\\Exception\\NotFoundHttpException')) {
    eval('namespace Tustin\\Haste\\Exception; final class NotFoundHttpException extends \RuntimeException {}');
}

final class PsnGameLookupServiceTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT NOT NULL, name TEXT NOT NULL, platform TEXT)');
    }

    public function testLookupByGameIdReturnsRawTrophyData(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (42, 'NPWR12345_00', 'Example Game')");

        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $capturedCalls = [];

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$capturedCalls): object {
                return new GameLookupStubClient(
                    profileHandler: function (string $path, array $query, array $headers) use (&$capturedCalls): object {
                        $capturedCalls[] = ['path' => $path, 'query' => $query, 'headers' => $headers];
                        return (object) [
                            'trophies' => [
                                (object) [
                                    'trophyGroupId' => 'all',
                                    'trophyGroupName' => 'Base',
                                    'trophyGroupDetail' => '',
                                    'trophyGroupIconUrl' => 'https://example.com/group.png',
                                    'trophyId' => 1,
                                    'trophyName' => 'Bronze Trophy',
                                ],
                            ],
                        ];
                    }
                );
            }
        );

        $result = $service->lookupByGameId(' 42 ');

        $this->assertSame(
            'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/NPWR12345_00/trophyGroups/all/trophies',
            $capturedCalls[0]['path']
        );
        $this->assertSame(['npLanguage' => 'en-US'], $capturedCalls[0]['query']);
        $this->assertSame(['content-type' => 'application/json'], $capturedCalls[0]['headers']);
        $this->assertSame(42, $result['game']['id']);
        $this->assertSame('NPWR12345_00', $result['game']['npCommunicationId']);
        $this->assertSame('Example Game', $result['game']['name']);
        $this->assertSame('Bronze Trophy', $result['trophyData']['trophyGroups'][0]['trophies'][0]['trophyName']);
    }

    public function testLookupByGameIdReturnsNotFoundErrorForMissingGame(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        try {
            $service->lookupByGameId('999');
            $this->fail('Expected PsnGameLookupException to be thrown.');
        } catch (PsnGameLookupException $exception) {
            $this->assertSame('Game ID "999" was not found.', $exception->getMessage());
        }
    }

    public function testLookupByGameIdPrefersTrophy2ForPs5Platforms(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name, platform) VALUES (77, 'NPWR77777_00', 'PS5 Game', 'PS5')");

        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);
        $attempts = [];

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$attempts): object {
                return new GameLookupStubClient(
                    profileHandler: static function (string $path, array $query) use (&$attempts): object {
                        $attempts[] = $query;

                        return (object) ['trophies' => [(object) ['trophyGroupId' => 'all', 'trophyId' => 1]]];
                    }
                );
            }
        );

        $service->lookupByGameId('77');

        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy2'], $attempts[0]);
    }

    public function testFetchTrophyDataForNpCommunicationIdUsesProvidedAuthenticatedClient(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $loginAttempts = 0;
        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (): object => (object) ['trophies' => []],
            loginHandler: static function () use (&$loginAttempts): void {
                $loginAttempts++;
            }
        );

        $result = $service->fetchTrophyDataForNpCommunicationId('NPWR00000_00', $providedClient);

        $this->assertSame([], $result['trophies']);
        $this->assertSame(0, $loginAttempts);
    }

    public function testFetchTrophyDataForNpCommunicationIdPreservesGroupedPayloadWhenFlatTrophiesMissing(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (): object => (object) [
                'trophyGroups' => [
                    (object) [
                        'trophyGroupId' => 'all',
                        'trophyGroupName' => 'Base',
                        'trophies' => [
                            (object) [
                                'trophyId' => 1,
                                'trophyGroupId' => 'all',
                                'trophyName' => 'Bronze Trophy',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $result = $service->fetchTrophyDataForNpCommunicationId('NPWR00000_00', $providedClient);

        $this->assertArrayNotHasKey('trophies', $result);
        $this->assertSame('all', $result['trophyGroups'][0]['trophyGroupId']);
        $this->assertSame('Bronze Trophy', $result['trophyGroups'][0]['trophies'][0]['trophyName']);
    }

    public function testFetchTrophyDataForNpCommunicationIdPreservesApiGroupedPayloadWhenFlatTrophiesExist(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (): object => (object) [
                'trophyGroups' => [
                    (object) [
                        'trophyGroupId' => 'all',
                        'trophyGroupName' => 'Base Group Name',
                        'trophyGroupDetail' => 'Base Group Detail',
                        'trophyGroupIconUrl' => 'https://example.com/group-icon.png',
                        'trophies' => [
                            (object) [
                                'trophyId' => 1,
                                'trophyGroupId' => 'all',
                                'trophyName' => 'Bronze Trophy',
                            ],
                        ],
                    ],
                ],
                'trophies' => [
                    (object) [
                        'trophyId' => 1,
                        'trophyGroupId' => 'all',
                        'trophyGroupName' => '',
                        'trophyGroupDetail' => '',
                        'trophyGroupIconUrl' => '',
                        'trophyName' => 'Bronze Trophy',
                    ],
                ],
            ]
        );

        $result = $service->fetchTrophyDataForNpCommunicationId('NPWR00000_00', $providedClient);

        $this->assertSame('Base Group Name', $result['trophyGroups'][0]['trophyGroupName']);
        $this->assertSame('Base Group Detail', $result['trophyGroups'][0]['trophyGroupDetail']);
        $this->assertSame('https://example.com/group-icon.png', $result['trophyGroups'][0]['trophyGroupIconUrl']);
    }

    public function testFetchTrophyDataForNpCommunicationIdAcceptsMatchingPayloadNpCommunicationId(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (string $path): object => str_ends_with($path, '/all/trophies')
                ? (object) [
                    'trophies' => [
                        (object) [
                            'trophyGroupId' => 'all',
                            'trophyId' => 1,
                            'trophyIconUrl' => 'https://image.api.playstation.com/trophy/np/NPWR12345_00_HASH/ICON.PNG',
                        ],
                    ],
                ]
                : (object) [
                    'trophyGroups' => [
                        (object) [
                            'npCommunicationId' => 'NPWR12345_00',
                            'trophyGroupId' => 'all',
                        ],
                    ],
                ]
        );

        $result = $service->fetchTrophyDataForNpCommunicationId(' NPWR12345_00 ', $providedClient);

        $this->assertSame(1, $result['trophyGroups'][0]['trophies'][0]['trophyId']);
    }

    public function testFetchTrophyDataForNpCommunicationIdThrowsForMismatchedPayloadNpCommunicationId(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (string $path): object => str_ends_with($path, '/all/trophies')
                ? (object) [
                    'trophies' => [
                        (object) [
                            'trophyGroupId' => 'all',
                            'trophyIconUrl' => 'https://image.api.playstation.com/trophy/np/NPWR99999_00_HASH/ICON.PNG',
                        ],
                    ],
                ]
                : (object) [
                    'trophyGroups' => [],
                ]
        );

        try {
            $service->fetchTrophyDataForNpCommunicationId('NPWR12345_00', $providedClient);
            $this->fail('Expected PsnGameLookupException to be thrown for mismatched payload ID.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "all/trophies"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }

    public function testFetchTrophyDataForNpCommunicationIdDoesNotFailWhenPayloadHasNoDetectableNpCommunicationId(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (string $path): object => str_ends_with($path, '/all/trophies')
                ? (object) [
                    'trophies' => [
                        (object) [
                            'trophyGroupId' => 'all',
                            'trophyId' => 1,
                        ],
                    ],
                ]
                : (object) [
                    'trophyGroups' => [
                        (object) [
                            'trophyGroupId' => 'all',
                        ],
                    ],
                ]
        );

        $result = $service->fetchTrophyDataForNpCommunicationId('NPWR12345_00', $providedClient);

        $this->assertSame(1, $result['trophyGroups'][0]['trophies'][0]['trophyId']);
    }

    public function testFetchTrophyDataForNpCommunicationIdThrowsForMismatchedTrophyGroupsNpCommunicationId(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (string $path): object => str_ends_with($path, '/all/trophies')
                ? (object) [
                    'trophies' => [
                        (object) [
                            'trophyGroupId' => 'all',
                            'trophyId' => 1,
                        ],
                    ],
                ]
                : (object) [
                    'trophyGroups' => [
                        (object) [
                            'trophyGroupId' => 'all',
                            'npCommunicationId' => 'NPWR99999_00',
                        ],
                    ],
                ]
        );

        try {
            $service->fetchTrophyDataForNpCommunicationId('NPWR12345_00', $providedClient);
            $this->fail('Expected PsnGameLookupException to be thrown for mismatched trophyGroups payload ID.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "trophyGroups"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }

    public function testFetchTrophyDataForNpCommunicationIdThrowsWhenPayloadContainsConflictingNpCommunicationIds(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (string $path): object => str_ends_with($path, '/all/trophies')
                ? (object) [
                    'npCommunicationId' => 'NPWR12345_00',
                    'trophies' => [
                        (object) [
                            'trophyGroupId' => 'all',
                            'trophyId' => 1,
                            'npCommunicationId' => 'NPWR99999_00',
                        ],
                    ],
                ]
                : (object) [
                    'trophyGroups' => [],
                ]
        );

        try {
            $service->fetchTrophyDataForNpCommunicationId('NPWR12345_00', $providedClient);
            $this->fail('Expected PsnGameLookupException when payload contains conflicting npCommunicationIds.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "all/trophies"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }

    public function testFetchTrophyDataForNpCommunicationIdThrowsWhenTrophyGroupsContainConflictingNestedIds(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $providedClient = new GameLookupStubClient(
            profileHandler: static fn (string $path): object => str_ends_with($path, '/all/trophies')
                ? (object) [
                    'trophies' => [
                        (object) [
                            'trophyGroupId' => 'all',
                            'trophyId' => 1,
                        ],
                    ],
                ]
                : (object) [
                    'trophyGroups' => [
                        (object) [
                            'npCommunicationId' => 'NPWR12345_00',
                            'trophies' => [
                                (object) [
                                    'trophyGroupId' => 'all',
                                    'trophyId' => 2,
                                    'trophyIconUrl' => 'https://image.api.playstation.com/trophy/np/NPWR99999_00_HASH/ICON.PNG',
                                ],
                            ],
                        ],
                    ],
                ]
        );

        try {
            $service->fetchTrophyDataForNpCommunicationId('NPWR12345_00', $providedClient);
            $this->fail('Expected PsnGameLookupException when trophyGroups contains conflicting nested IDs.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString(
                'PSN response integrity check failed for endpoint "trophyGroups"',
                $exception->getMessage()
            );
            $this->assertStringContainsString('NPWR12345_00', $exception->getMessage());
            $this->assertStringContainsString('NPWR99999_00', $exception->getMessage());
        }
    }

    public function testRequestHandlerReturnsValidationErrorMessage(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            static fn (): object => new GameLookupStubClient()
        );

        $handled = PsnGameLookupRequestHandler::handle($service, 'foo');

        $this->assertSame('foo', $handled->getNormalizedGameId());
        $this->assertSame(null, $handled->getResult());
        $this->assertSame('Game ID must be a numeric value.', $handled->getErrorMessage());
    }

    public function testLookupByGameIdRetriesWhenStatusCodeIsOnlyAvailableOnPreviousException(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (54139, 'NPWR51065_00', 'Retry Game')");

        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $attempts = [];

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$attempts): object {
                return new GameLookupStubClient(
                    profileHandler: function (string $path, array $query) use (&$attempts): object {
                        $attempts[] = $query;

                        if (count($attempts) < 3) {
                            throw new RuntimeException(
                                'Wrapped request failure',
                                0,
                                new GameLookupHttpException(404)
                            );
                        }

                        return (object) ['trophies' => [(object) ['trophyGroupId' => 'all', 'trophyId' => 7]]];
                    }
                );
            }
        );

        $result = $service->lookupByGameId('54139');

        $this->assertSame(['npLanguage' => 'en-US'], $attempts[0]);
        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy'], $attempts[1]);
        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy2'], $attempts[2]);
        $this->assertSame(7, $result['trophyData']['trophyGroups'][0]['trophies'][0]['trophyId']);
    }

    public function testFetchTrophyDataForNpCommunicationIdPinsWinningVariantAcrossBothEndpoints(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);
        $attempts = [];

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$attempts): object {
                return new GameLookupStubClient(
                    profileHandler: static function (string $path, array $query) use (&$attempts): object {
                        $attempts[] = ['path' => $path, 'query' => $query];

                        if (str_ends_with($path, '/all/trophies')) {
                            if ($query === ['npLanguage' => 'en-US']) {
                                throw new GameLookupHttpException(404);
                            }

                            if ($query === ['npLanguage' => 'en-US', 'npServiceName' => 'trophy']) {
                                return (object) ['trophies' => [(object) ['trophyGroupId' => 'all', 'trophyId' => 41]]];
                            }
                        }

                        if (str_ends_with($path, '/trophyGroups')
                            && $query === ['npLanguage' => 'en-US', 'npServiceName' => 'trophy']) {
                            return (object) ['trophyGroups' => [(object) ['trophyGroupId' => 'all']]];
                        }

                        throw new RuntimeException('Unexpected request variant.');
                    }
                );
            }
        );

        $result = $service->fetchTrophyDataForNpCommunicationId('NPWR00000_00');

        $this->assertSame(41, $result['trophyGroups'][0]['trophies'][0]['trophyId']);
        $this->assertCount(3, $attempts);
        $this->assertSame(['npLanguage' => 'en-US'], $attempts[0]['query']);
        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy'], $attempts[1]['query']);
        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy'], $attempts[2]['query']);
        $this->assertTrue(str_ends_with($attempts[2]['path'], '/trophyGroups'));
    }

    public function testFetchTrophyDataForNpCommunicationIdRetriesBothEndpointsUnderSingleFallbackVariant(): void
    {
        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);
        $attempts = [];

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$attempts): object {
                return new GameLookupStubClient(
                    profileHandler: static function (string $path, array $query) use (&$attempts): object {
                        $attempts[] = ['path' => $path, 'query' => $query];

                        if (str_ends_with($path, '/all/trophies') && $query === ['npLanguage' => 'en-US']) {
                            return (object) ['trophies' => [(object) ['trophyGroupId' => 'all', 'trophyId' => 51]]];
                        }

                        if (str_ends_with($path, '/trophyGroups') && $query === ['npLanguage' => 'en-US']) {
                            throw new GameLookupHttpException(404);
                        }

                        if (str_ends_with($path, '/all/trophies')
                            && $query === ['npLanguage' => 'en-US', 'npServiceName' => 'trophy']) {
                            return (object) ['trophies' => [(object) ['trophyGroupId' => 'all', 'trophyId' => 52]]];
                        }

                        if (str_ends_with($path, '/trophyGroups')
                            && $query === ['npLanguage' => 'en-US', 'npServiceName' => 'trophy']) {
                            return (object) ['trophyGroups' => [(object) ['trophyGroupId' => 'all']]];
                        }

                        throw new RuntimeException('Unexpected request variant.');
                    }
                );
            }
        );

        $result = $service->fetchTrophyDataForNpCommunicationId('NPWR00000_00');

        $this->assertSame(52, $result['trophyGroups'][0]['trophies'][0]['trophyId']);
        $this->assertCount(4, $attempts);
        $this->assertTrue(str_ends_with($attempts[0]['path'], '/all/trophies'));
        $this->assertSame(['npLanguage' => 'en-US'], $attempts[0]['query']);
        $this->assertTrue(str_ends_with($attempts[1]['path'], '/trophyGroups'));
        $this->assertSame(['npLanguage' => 'en-US'], $attempts[1]['query']);
        $this->assertTrue(str_ends_with($attempts[2]['path'], '/all/trophies'));
        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy'], $attempts[2]['query']);
        $this->assertTrue(str_ends_with($attempts[3]['path'], '/trophyGroups'));
        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy'], $attempts[3]['query']);
    }

    public function testLookupByGameIdRetriesForKnownHasteExceptionWithoutStatusCode(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (54139, 'NPWR51065_00', 'Retry Game')");

        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $attempts = [];

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$attempts): object {
                return new GameLookupStubClient(
                    profileHandler: function (string $path, array $query) use (&$attempts): object {
                        $attempts[] = $query;

                        if (count($attempts) === 1) {
                            throw new \Tustin\Haste\Exception\NotFoundHttpException();
                        }

                        return (object) ['trophies' => [(object) ['trophyGroupId' => 'all', 'trophyId' => 8]]];
                    }
                );
            }
        );

        $result = $service->lookupByGameId('54139');

        $this->assertSame(['npLanguage' => 'en-US'], $attempts[0]);
        $this->assertSame(['npLanguage' => 'en-US', 'npServiceName' => 'trophy'], $attempts[1]);
        $this->assertSame(8, $result['trophyData']['trophyGroups'][0]['trophies'][0]['trophyId']);
    }

    public function testLookupByGameIdDoesNotRetryKnownHasteExceptionWhenStatusCodeIsNonRetryable(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (54139, 'NPWR51065_00', 'Retry Game')");

        $worker = new Worker(1, 'valid-npsso', '', new DateTimeImmutable('2024-01-01T00:00:00+00:00'), null);

        $attempts = [];

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$attempts): object {
                return new GameLookupStubClient(
                    profileHandler: function (string $path, array $query) use (&$attempts): object {
                        $attempts[] = $query;

                        throw new \Tustin\Haste\Exception\NotFoundHttpException(
                            'Known haste exception with non-retryable status',
                            0,
                            new GameLookupHttpException(500)
                        );
                    }
                );
            }
        );

        try {
            $service->lookupByGameId('54139');
            $this->fail('Expected PsnGameLookupException to be thrown.');
        } catch (PsnGameLookupException $exception) {
            $this->assertStringContainsString('Failed to retrieve trophy data from PlayStation Network', $exception->getMessage());
            $this->assertCount(1, $attempts);
            $this->assertSame(['npLanguage' => 'en-US'], $attempts[0]);
        }
    }
}

final class GameLookupStubClient
{
    /** @var callable(string, array, array): object */
    private $profileHandler;

    /** @var callable(string): void */
    private $loginHandler;

    public function __construct(?callable $profileHandler = null, ?callable $loginHandler = null)
    {
        $this->profileHandler = $profileHandler ?? static fn (): object => (object) [];
        $this->loginHandler = $loginHandler ?? static function (): void {
        };
    }

    public function loginWithNpsso(string $npsso): void
    {
        ($this->loginHandler)($npsso);
    }

    public function get(string $path = '', array $query = [], array $headers = []): object
    {
        return ($this->profileHandler)($path, $query, $headers);
    }
}

final class GameLookupHttpException extends RuntimeException
{
    public function __construct(private readonly int $statusCode)
    {
        parent::__construct('HTTP error', $statusCode);
    }

    public function getResponse(): object
    {
        return new class ($this->statusCode) {
            public function __construct(private readonly int $statusCode)
            {
            }

            public function getStatusCode(): int
            {
                return $this->statusCode;
            }
        };
    }
}
