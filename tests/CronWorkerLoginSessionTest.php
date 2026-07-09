<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/CronWorkerLoginSession.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/WorkerScanCoordinator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerService.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';

final class CronWorkerLoginSessionTest extends TestCase
{
    private PDO $database;
    private Psn100Logger $logger;
    private WorkerScanCoordinator $workerScanCoordinator;
    /** @var list<int> */
    private array $sleptSeconds = [];

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE setting (id INTEGER PRIMARY KEY, refresh_token TEXT, npsso TEXT, scanning TEXT, scan_progress TEXT)'
        );
        $this->database->exec('CREATE TABLE log (message TEXT NOT NULL)');
        $this->database->exec(
            "INSERT INTO setting (id, refresh_token, npsso, scanning, scan_progress) VALUES (1, '', 'npsso-token', 'queued-player', NULL)"
        );

        $this->logger = new Psn100Logger($this->database);
        $this->sleptSeconds = [];
        $this->workerScanCoordinator = new WorkerScanCoordinator($this->database);
    }

    public function testAuthenticateThrowsWhenWorkerDoesNotExist(): void
    {
        $loginSession = $this->createLoginSession(
            new PlayStationWorkerAuthenticator(
                static fn (): array => [],
                static fn (): object => self::createAuthenticatedClientStub(),
            ),
        );

        try {
            $loginSession->authenticate(999);
            $this->fail('Expected RuntimeException for unknown worker id.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Worker 999 not found in setting table', $exception->getMessage());
        }

        $statement = $this->database->query('SELECT message FROM log');
        $message = $statement !== false ? $statement->fetchColumn() : false;
        $this->assertSame('Worker 999 not found in setting table', $message !== false ? (string) $message : '');
    }

    public function testAuthenticateRetriesAfterTypeErrorThenSucceeds(): void
    {
        $attempts = 0;
        $authenticator = new PlayStationWorkerAuthenticator(
            static fn (): array => [],
            static function () use (&$attempts): object {
                $attempts++;

                if ($attempts === 1) {
                    throw new TypeError('Unexpected API response shape');
                }

                return self::createAuthenticatedClientStub();
            },
        );

        $loginSession = $this->createLoginSession($authenticator);
        $result = $loginSession->authenticate(1);

        $this->assertSame(2, $attempts);
        $this->assertSame([60], $this->sleptSeconds);
        $this->assertTrue(is_object($result['client']));
        $this->assertSame('1', (string) ($result['worker']['id'] ?? ''));

        $setting = $this->fetchSetting(1);
        $scanProgress = json_decode((string) $setting['scan_progress'], true);
        $this->assertTrue(is_array($scanProgress));
        $this->assertSame(
            'Encountered a login problem. Waiting 1 minute before retrying.',
            (string) ($scanProgress['title'] ?? '')
        );
    }

    public function testAuthenticateReleasesWorkerAndWaitsAfterLoginException(): void
    {
        $attempts = 0;
        $loginSession = new CronWorkerLoginSession(
            $this->database,
            new PlayStationWorkerAuthenticator(
                static fn (): array => [],
                static function () use (&$attempts): object {
                    $attempts++;

                    if ($attempts === 1) {
                        throw new RuntimeException('login failed');
                    }

                    return self::createAuthenticatedClientStub();
                },
            ),
            $this->workerScanCoordinator,
            $this->logger,
            function (int $seconds): void {
                $this->sleptSeconds[] = $seconds;
            },
        );

        $result = $loginSession->authenticate(1);

        $this->assertSame(2, $attempts);
        $this->assertSame([60 * 30], $this->sleptSeconds);
        $this->assertTrue(is_object($result['client']));

        $setting = $this->fetchSetting(1);
        $this->assertSame('1', (string) $setting['scanning']);
        $this->assertSame(null, $setting['scan_progress']);

        $statement = $this->database->query('SELECT message FROM log ORDER BY rowid DESC LIMIT 1');
        $message = $statement !== false ? $statement->fetchColumn() : false;
        $this->assertSame("Can't login with worker 1", $message !== false ? (string) $message : '');
    }

    private function createLoginSession(PlayStationWorkerAuthenticator $authenticator): CronWorkerLoginSession
    {
        return new CronWorkerLoginSession(
            $this->database,
            $authenticator,
            $this->workerScanCoordinator,
            $this->logger,
            function (int $seconds): void {
                $this->sleptSeconds[] = $seconds;
            },
        );
    }

    private static function createAuthenticatedClientStub(): object
    {
        static $client = new class {
            public function loginWithNpsso(string $npsso): void
            {
            }

            public function getRefreshToken(): object
            {
                return new class {
                    public function getToken(): string
                    {
                        return '';
                    }
                };
            }
        };

        return $client;
    }

    /**
     * @return array{scanning: mixed, scan_progress: mixed}
     */
    private function fetchSetting(int $workerId): array
    {
        $settingQuery = $this->database->query(
            sprintf('SELECT scanning, scan_progress FROM setting WHERE id = %d', $workerId)
        );
        $setting = $settingQuery !== false ? $settingQuery->fetch(PDO::FETCH_ASSOC) : false;
        $this->assertTrue(is_array($setting));

        return $setting;
    }
}
