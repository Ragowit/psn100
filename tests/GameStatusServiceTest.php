<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameStatusService.php';
require_once __DIR__ . '/TestCase.php';

final class GameStatusServiceTest extends TestCase
{
    private PDO $database;

    private GameStatusService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, status INTEGER NOT NULL)');
        $this->database->exec('CREATE TABLE psn100_change (id INTEGER PRIMARY KEY AUTOINCREMENT, change_type TEXT, param_1 INTEGER)');

        $this->service = new GameStatusService($this->database);
    }

    protected function tearDown(): void
    {
        unset($this->service);
        unset($this->database);
    }

    public function testUpdateGameStatusUpdatesDatabaseAndReturnsStatusText(): void
    {
        $this->database->exec('INSERT INTO trophy_title (id, status) VALUES (1, 0)');

        $result = $this->service->updateGameStatus(1, 1);

        $this->assertSame('delisted', $result);
        $this->assertSame(
            1,
            (int) $this->database->query('SELECT status FROM trophy_title WHERE id = 1')->fetchColumn()
        );

        $changeLog = $this->database
            ->query('SELECT change_type, param_1 FROM psn100_change ORDER BY id')
            ->fetch(PDO::FETCH_ASSOC);

        $changeLog['param_1'] = (int) $changeLog['param_1'];

        $this->assertSame(
            ['change_type' => 'GAME_DELISTED', 'param_1' => 1],
            $changeLog
        );
    }

    public function testUpdateGameStatusDefaultsToNormalWhenStatusIsUnknown(): void
    {
        $this->database->exec('INSERT INTO trophy_title (id, status) VALUES (42, 0)');

        $result = $this->service->updateGameStatus(42, 99);

        $this->assertSame('normal', $result);
        $this->assertSame(
            99,
            (int) $this->database->query('SELECT status FROM trophy_title WHERE id = 42')->fetchColumn()
        );

        $changeLog = $this->database
            ->query('SELECT change_type, param_1 FROM psn100_change ORDER BY id')
            ->fetch(PDO::FETCH_ASSOC);

        $changeLog['param_1'] = (int) $changeLog['param_1'];

        $this->assertSame(
            ['change_type' => 'GAME_NORMAL', 'param_1' => 42],
            $changeLog
        );
    }

    public function testUpdateGameStatusThrowsExceptionWhenGameIdIsNegative(): void
    {
        try {
            $this->service->updateGameStatus(-1, 1);
            $this->fail('Expected InvalidArgumentException to be thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Game ID must be a non-negative integer.', $exception->getMessage());
        }
    }

    public function testUpdateGameStatusRollsBackChangesWhenLoggingFails(): void
    {
        $this->database->exec('INSERT INTO trophy_title (id, status) VALUES (7, 0)');
        $this->database->exec('DROP TABLE psn100_change');
        $this->database->exec('CREATE TABLE psn100_change (id INTEGER PRIMARY KEY AUTOINCREMENT)');

        try {
            $this->service->updateGameStatus(7, 4);
            $this->fail('Expected an exception to be thrown.');
        } catch (Throwable $exception) {
            // Exception is expected because logging fails due to missing columns.
        }

        $this->assertSame(
            0,
            (int) $this->database->query('SELECT status FROM trophy_title WHERE id = 7')->fetchColumn()
        );

        $changeCount = $this->database
            ->query('SELECT COUNT(*) FROM psn100_change')
            ->fetchColumn();

        $this->assertSame(0, (int) $changeCount);
    }
}
