<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/LogEntryFormatter.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/LogService.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class AdminLogServiceTest extends TestCase
{
    public function testFetchEntriesForPageReturnsFormattedEntries(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT)');
        $database->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, time TEXT, message TEXT)');

        $database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (59688, 'NPWR47160_00', 'Food Truck Kingdom')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-01 10:00:00', 'SET VERSION for Food Truck Kingdom. NPWR47160_00, default, Food Truck Kingdom')");

        $formatter = new LogEntryFormatter($database, new Utility());
        $service = new LogService($database, $formatter);

        $entries = $service->fetchEntriesForPage(1, 10);

        $this->assertCount(1, $entries);
        $this->assertSame(1, $entries[0]->getId());
        $this->assertSame('2024-05-01 10:00:00', $entries[0]->getTime()->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $entries[0]->getTime()->getTimezone()->getName());
        $this->assertStringContainsString('/game/59688-food-truck-kingdom', $entries[0]->getFormattedMessage());
    }

    public function testFetchEntriesForPageOrdersAscendingById(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT)');
        $database->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, time TEXT, message TEXT)');

        $database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (1, 'NPWR00001_00', 'Game One')");
        $database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (2, 'NPWR00002_00', 'Game Two')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-01 10:00:00', 'First message for Game One. NPWR00001_00, default, Game One')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-02 10:00:00', 'Second message for Game Two. NPWR00002_00, default, Game Two')");

        $formatter = new LogEntryFormatter($database, new Utility());
        $service = new LogService($database, $formatter);

        $entries = $service->fetchEntriesForPage(1, 10);

        $this->assertCount(2, $entries);
        $this->assertSame([1, 2], [$entries[0]->getId(), $entries[1]->getId()]);
    }

    public function testDeleteLogByIdRemovesEntry(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT)');
        $database->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, time TEXT, message TEXT)');

        $database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (42, 'NPWR00042_00', 'Example Game')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-05-01 10:00:00', 'Recorded new trophy_title_history entry 123 for trophy_title.id 42')");

        $formatter = new LogEntryFormatter($database, new Utility());
        $service = new LogService($database, $formatter);

        $this->assertSame(1, $service->countEntries());
        $this->assertTrue($service->deleteLogById(1));
        $this->assertSame(0, $service->countEntries());
        $this->assertFalse($service->deleteLogById(1));
    }

    public function testFallsBackToLegacyLogTable(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT)');
        $database->exec('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT, time TEXT, message TEXT)');

        $database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (10, 'NPWR00010_00', 'Legacy Game')");
        $database->exec("INSERT INTO log (time, message) VALUES ('2024-01-01 00:00:00', 'New trophies added for Legacy Game. NPWR00010_00, default, Legacy Group')");

        $formatter = new LogEntryFormatter($database, new Utility());
        $service = new LogService($database, $formatter);

        $entries = $service->fetchEntriesForPage(1, 5);

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('/game/10-legacy-game', $entries[0]->getFormattedMessage());
    }
}
