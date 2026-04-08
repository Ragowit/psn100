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

        $capturedPath = null;
        $capturedQuery = null;
        $capturedHeaders = null;

        $service = new PsnGameLookupService(
            $this->database,
            static fn (): array => [$worker],
            function () use (&$capturedPath, &$capturedQuery, &$capturedHeaders): object {
                return new GameLookupStubClient(
                    profileHandler: function (string $path, array $query, array $headers) use (&$capturedPath, &$capturedQuery, &$capturedHeaders): object {
                        $capturedPath = $path;
                        $capturedQuery = $query;
                        $capturedHeaders = $headers;
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
            $capturedPath
        );
        $this->assertSame(['npLanguage' => 'en-US'], $capturedQuery);
        $this->assertSame(['content-type' => 'application/json'], $capturedHeaders);
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
