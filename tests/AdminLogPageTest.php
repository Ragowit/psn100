<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/LogEntryFormatter.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/LogService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/LogPage.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class AdminLogPageTest extends TestCase
{
    public function testHandleDeletesMultipleLogEntries(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT)');
        $database->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, time TEXT, message TEXT)');

        $database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (1, 'NPWR00001_00', 'Example Game')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-01 10:00:00', 'First entry for deletion test')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-01 11:00:00', 'Second entry for deletion test')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-01 12:00:00', 'Third entry for deletion test')");

        $formatter = new LogEntryFormatter($database, new Utility());
        $service = new LogService($database, $formatter);
        $page = new LogPage($service, 10);

        $result = $page->handle([], ['delete_ids' => ['1', '2', 'invalid', '2'], 'delete_selected' => '1'], 'POST');

        $this->assertSame('Deleted 2 log entries (IDs: 1, 2).', $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());
        $this->assertSame(1, $service->countEntries());
    }

    public function testHandleReportsWhenNoSelectedEntriesAreDeleted(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT)');
        $database->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, time TEXT, message TEXT)');

        $database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (5, 'NPWR00005_00', 'Example Game 5')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-01 10:00:00', 'Only entry remaining after failed delete')");

        $formatter = new LogEntryFormatter($database, new Utility());
        $service = new LogService($database, $formatter);
        $page = new LogPage($service, 10);

        $result = $page->handle([], ['delete_ids' => ['99', '0', 'abc'], 'delete_selected' => '1'], 'POST');

        $this->assertSame('No matching log entries were found for the selected IDs.', $result->getErrorMessage());
        $this->assertSame(null, $result->getSuccessMessage());
        $this->assertSame(1, $service->countEntries());
    }
}
