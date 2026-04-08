<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnGameLookupService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PsnGameLookupRequestHandler.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/Worker.php';

final class PsnGameLookupServiceTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT NOT NULL, name TEXT NOT NULL)');
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
                                (object) ['trophyId' => 1, 'trophyName' => 'Bronze Trophy'],
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
        $this->assertSame('Bronze Trophy', $result['trophyData']['trophies'][0]['trophyName']);
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
