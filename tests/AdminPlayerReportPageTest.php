<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PlayerReportAdminService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PlayerReportAdminPage.php';

final class AdminPlayerReportPageTest extends TestCase
{
    public function testHandleDeletesReportViaPost(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE player (account_id INTEGER PRIMARY KEY, online_id TEXT)');
        $database->exec('CREATE TABLE player_report (report_id INTEGER PRIMARY KEY, account_id INTEGER, explanation TEXT)');
        $database->exec("INSERT INTO player (account_id, online_id) VALUES (1, 'ExampleUser')");
        $database->exec("INSERT INTO player_report (report_id, account_id, explanation) VALUES (5, 1, 'Cheating')");

        $service = new PlayerReportAdminService($database);
        $page = new PlayerReportAdminPage($service);

        $result = $page->handle([], ['delete_id' => '5'], 'POST');

        $this->assertSame('Report 5 deleted successfully.', $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());
        $this->assertSame([], $result->getReportedPlayers());
    }

    public function testHandleIgnoresGetDeleteRequests(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE player (account_id INTEGER PRIMARY KEY, online_id TEXT)');
        $database->exec('CREATE TABLE player_report (report_id INTEGER PRIMARY KEY, account_id INTEGER, explanation TEXT)');
        $database->exec("INSERT INTO player (account_id, online_id) VALUES (1, 'ExampleUser')");
        $database->exec("INSERT INTO player_report (report_id, account_id, explanation) VALUES (5, 1, 'Cheating')");

        $service = new PlayerReportAdminService($database);
        $page = new PlayerReportAdminPage($service);

        $result = $page->handle(['delete' => '5'], [], 'GET');

        $this->assertSame(null, $result->getSuccessMessage());
        $this->assertSame(null, $result->getErrorMessage());
        $this->assertCount(1, $result->getReportedPlayers());
    }
}
