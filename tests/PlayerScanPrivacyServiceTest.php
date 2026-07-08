<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerStatus.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/WorkerScanCoordinator.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanPrivacyService.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTrophySummaryAccessResult.php';

final class PlayerScanPrivacyServiceTest extends TestCase
{
    private PDO $database;

    private PlayerScanPrivacyService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, scanning TEXT, scan_progress TEXT)');
        $this->database->exec('CREATE TABLE player_queue (online_id TEXT PRIMARY KEY)');
        $this->database->exec(
            'CREATE TABLE player (
                account_id INTEGER PRIMARY KEY,
                online_id TEXT NOT NULL,
                status INTEGER NOT NULL DEFAULT 99,
                last_updated_date TEXT
            )'
        );
        $this->database->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);

        $this->service = new PlayerScanPrivacyService(
            $this->database,
            new WorkerScanCoordinator($this->database),
            static function (int $seconds): void {
            },
        );
    }

    public function testMarkAsPrivateByOnlineIdUpdatesStatusAndRemovesQueueEntry(): void
    {
        $this->database->exec(
            "INSERT INTO player (account_id, online_id, status) VALUES (100, 'private-player', 99)"
        );
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('private-player')");

        $this->service->markAsPrivateByOnlineId('private-player');

        $status = $this->database->query("SELECT status FROM player WHERE online_id = 'private-player'")->fetchColumn();
        $queueCount = $this->database->query('SELECT COUNT(*) FROM player_queue')->fetchColumn();

        $this->assertSame((string) PlayerStatus::PRIVATE_PROFILE->value, (string) $status);
        $this->assertSame('0', (string) $queueCount);
    }

    public function testMarkAsPrivateByOnlineIdDoesNotOverrideFlaggedPlayers(): void
    {
        $this->database->exec(
            "INSERT INTO player (account_id, online_id, status) VALUES (101, 'flagged-player', 1)"
        );

        $this->service->markAsPrivateByOnlineId('flagged-player');

        $status = $this->database->query("SELECT status FROM player WHERE online_id = 'flagged-player'")->fetchColumn();

        $this->assertSame((string) PlayerStatus::FLAGGED->value, (string) $status);
    }

    public function testMarkAsPrivateByAccountIdUpdatesStatusAndRemovesQueueEntry(): void
    {
        $this->database->exec(
            "INSERT INTO player (account_id, online_id, status) VALUES (200, 'renamed-player', 99)"
        );
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('queue-name')");

        $this->service->markAsPrivateByAccountId('200', 'queue-name');

        $status = $this->database->query('SELECT status FROM player WHERE account_id = 200')->fetchColumn();
        $queueCount = $this->database->query('SELECT COUNT(*) FROM player_queue')->fetchColumn();

        $this->assertSame((string) PlayerStatus::PRIVATE_PROFILE->value, (string) $status);
        $this->assertSame('0', (string) $queueCount);
    }

    public function testResolveTrophySummaryLevelReturnsAccessibleLevel(): void
    {
        $user = new class {
            public function trophySummary(): object
            {
                return new class {
                    public function level(): int
                    {
                        return 42;
                    }
                };
            }
        };

        $result = $this->service->resolveTrophySummaryLevel($user, 1);

        $this->assertTrue($result->isAccessible());
        $this->assertSame(42, $result->level);
    }

    public function testResolveTrophySummaryLevelMarksPrivateAfterRetryFailure(): void
    {
        $this->database->exec(
            "INSERT INTO player (account_id, online_id, status) VALUES (300, 'hidden-player', 99)"
        );
        $this->database->exec("INSERT INTO player_queue (online_id) VALUES ('hidden-player')");

        $user = new class {
            public int $attempts = 0;

            public function accountId(): string
            {
                return '300';
            }

            public function onlineId(): string
            {
                return 'hidden-player';
            }

            public function trophySummary(): object
            {
                $this->attempts++;

                throw new RuntimeException('profile is private');
            }
        };

        $result = $this->service->resolveTrophySummaryLevel($user, 1);

        $this->assertTrue($result->isPrivateProfile());
        $status = $this->database->query('SELECT status FROM player WHERE account_id = 300')->fetchColumn();
        $queueCount = $this->database->query('SELECT COUNT(*) FROM player_queue')->fetchColumn();

        $this->assertSame((string) PlayerStatus::PRIVATE_PROFILE->value, (string) $status);
        $this->assertSame('0', (string) $queueCount);
        $this->assertSame(2, $user->attempts);
    }

    public function testTrophySummaryAccessResultHelpers(): void
    {
        $accessible = PlayerScanTrophySummaryAccessResult::accessible(10);
        $private = PlayerScanTrophySummaryAccessResult::privateProfile();
        $abort = PlayerScanTrophySummaryAccessResult::abortScan();

        $this->assertTrue($accessible->isAccessible());
        $this->assertFalse($accessible->isPrivateProfile());
        $this->assertFalse($accessible->shouldAbortScan());

        $this->assertTrue($private->isPrivateProfile());
        $this->assertFalse($private->isAccessible());
        $this->assertFalse($private->shouldAbortScan());

        $this->assertTrue($abort->shouldAbortScan());
        $this->assertFalse($abort->isAccessible());
        $this->assertFalse($abort->isPrivateProfile());
    }
}
