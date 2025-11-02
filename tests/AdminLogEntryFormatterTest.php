<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Admin/LogEntryFormatter.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class AdminLogEntryFormatterTest extends TestCase
{
    private PDO $database;

    private LogEntryFormatter $formatter;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE trophy_title (id INTEGER PRIMARY KEY, np_communication_id TEXT, name TEXT)');

        $utility = new Utility();
        $this->formatter = new LogEntryFormatter($this->database, $utility);
    }

    public function testFormatsTrophyHistoryMessageWithGameHistoryLink(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (42, 'NPWR00042_00', 'Example Game')");

        $message = 'Recorded new trophy_title_history entry 123 for trophy_title.id 42';
        $formatted = $this->formatter->format($message);

        $this->assertStringContainsString('/game-history/42-example-game', $formatted);
        $this->assertStringContainsString('>42<', $formatted);
    }

    public function testFormatsSetVersionMessageWithLinks(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (59688, 'NPWR47160_00', 'Food Truck Kingdom')");

        $message = 'SET VERSION for Food Truck Kingdom. NPWR47160_00, default, Food Truck Kingdom';
        $formatted = $this->formatter->format($message);

        $this->assertStringContainsString('/game/59688-food-truck-kingdom', $formatted);
        $this->assertStringContainsString('/game/59688-food-truck-kingdom#default', $formatted);
    }

    public function testFormatsNewTrophiesMessageWithLinks(): void
    {
        $this->database->exec("INSERT INTO trophy_title (id, np_communication_id, name) VALUES (1234, 'NPWR99999_00', 'Sample Game')");

        $message = 'New trophies added for Sample Game. NPWR99999_00, default, Sample Group';
        $formatted = $this->formatter->format($message);

        $this->assertStringContainsString('/game/1234-sample-game', $formatted);
        $this->assertStringContainsString('/game/1234-sample-game#default', $formatted);
    }

    public function testFormatsSonyIssuesMessageWithPlayerLink(): void
    {
        $message = 'Sony issues with ExampleUser (123456789).';
        $formatted = $this->formatter->format($message);

        $this->assertStringContainsString('/player/ExampleUser', $formatted);
        $this->assertStringContainsString('>ExampleUser<', $formatted);
    }

    public function testReturnsEscapedMessageWhenNoFormatterMatches(): void
    {
        $message = 'Unknown <log> entry';
        $formatted = $this->formatter->format($message);

        $this->assertSame('Unknown &lt;log&gt; entry', $formatted);
    }
}
