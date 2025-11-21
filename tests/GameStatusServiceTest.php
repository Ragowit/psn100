<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameStatusService.php';

final class GameStatusServiceTest extends TestCase
{
    private PDO $database;
    private GameStatusService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT NOT NULL)');
        $this->database->exec('CREATE TABLE trophy_title_meta (np_communication_id TEXT PRIMARY KEY, status INTEGER NOT NULL, obsolete_ids TEXT NULL, psnprofiles_id TEXT NULL)');
        $this->database->exec('CREATE TABLE psn100_change (change_type TEXT NOT NULL, param_1 INTEGER NOT NULL)');
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id) VALUES (1, 'NPWR-1')");
        $this->database->exec("INSERT INTO trophy_title_meta (np_communication_id, status) VALUES ('NPWR-1', 0)");

        $this->service = new GameStatusService($this->database);
    }

    public function testUpdateGameStatusUpdatesDatabaseAndReturnsStatusText(): void
    {
        $statusText = $this->service->updateGameStatus(1, 1);

        $this->assertSame('delisted', $statusText);

        $status = $this->database
            ->query('SELECT status FROM trophy_title_meta WHERE np_communication_id = "NPWR-1"')
            ->fetchColumn();
        $this->assertSame(1, (int) $status);

        $changes = $this->database
            ->query('SELECT change_type, param_1 FROM psn100_change')
            ->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame(
            [
                ['change_type' => 'GAME_DELISTED', 'param_1' => 1],
            ],
            array_map(
                static fn (array $row): array => [
                    'change_type' => $row['change_type'],
                    'param_1' => (int) $row['param_1'],
                ],
                $changes
            )
        );
    }

    public function testUpdateGameStatusRollsBackWhenLoggingFails(): void
    {
        $this->database->exec('CREATE TRIGGER fail_insert BEFORE INSERT ON psn100_change BEGIN SELECT RAISE(ABORT, "log failure"); END;');

        try {
            $this->service->updateGameStatus(1, 3);
            $this->fail('Expected exception was not thrown.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('log failure', $exception->getMessage());
        }

        $status = $this->database
            ->query('SELECT status FROM trophy_title_meta WHERE np_communication_id = "NPWR-1"')
            ->fetchColumn();
        $this->assertSame(0, (int) $status);

        $changeCount = $this->database
            ->query('SELECT COUNT(*) FROM psn100_change')
            ->fetchColumn();
        $this->assertSame(0, (int) $changeCount);
    }

    public function testUpdateGameStatusRejectsNegativeGameId(): void
    {
        try {
            $this->service->updateGameStatus(-5, 1);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Game ID must be a non-negative integer.', $exception->getMessage());
        }
    }
}
