<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerSummaryService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerSummary.php';

final class PlayerSummaryServiceTest extends TestCase
{
    private PDO $database;
    private PlayerSummaryService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            'CREATE TABLE trophy_title (
                np_communication_id TEXT PRIMARY KEY,
                bronze INTEGER NOT NULL,
                silver INTEGER NOT NULL,
                gold INTEGER NOT NULL,
                platinum INTEGER NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL,
                obsolete_ids TEXT NULL,
                psnprofiles_id TEXT NULL,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_player (
                np_communication_id TEXT NOT NULL,
                account_id INTEGER NOT NULL,
                progress INTEGER NOT NULL,
                bronze INTEGER NOT NULL,
                silver INTEGER NOT NULL,
                gold INTEGER NOT NULL,
                platinum INTEGER NOT NULL,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                common INTEGER NOT NULL DEFAULT 0,
                uncommon INTEGER NOT NULL DEFAULT 0,
                rare INTEGER NOT NULL DEFAULT 0,
                epic INTEGER NOT NULL DEFAULT 0,
                legendary INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_common INTEGER NOT NULL DEFAULT 0,
                in_game_uncommon INTEGER NOT NULL DEFAULT 0,
                in_game_rare INTEGER NOT NULL DEFAULT 0,
                in_game_epic INTEGER NOT NULL DEFAULT 0,
                in_game_legendary INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->service = new PlayerSummaryService($this->database);
    }

    public function testGetSummaryAggregatesPlayerStatistics(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, bronze, silver, gold, platinum) VALUES\n" .
            "('NPWR001', 10, 5, 3, 1),\n" .
            "('NPWR002', 5, 2, 1, 0),\n" .
            "('NPWR003', 1, 1, 1, 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status) VALUES\n" .
            "('NPWR001', 0),\n" .
            "('NPWR002', 0),\n" .
            "('NPWR003', 1)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_player (np_communication_id, account_id, progress, bronze, silver, gold, platinum) VALUES\n" .
            "('NPWR001', 42, 75, 8, 4, 2, 1),\n" .
            "('NPWR002', 42, 100, 1, 1, 0, 0),\n" .
            "('NPWR003', 42, 100, 1, 1, 1, 0),\n" .
            "('NPWR001', 7, 50, 0, 0, 0, 0)"
        );

        $summary = $this->service->getSummary(42);

        $this->assertSame(2, $summary->getNumberOfGames());
        $this->assertSame(1, $summary->getNumberOfCompletedGames());
        $this->assertSame(87.5, $summary->getAverageProgress());
        $this->assertSame(10, $summary->getUnearnedTrophies());
    }

    public function testGetSummaryReturnsEmptySummaryWhenPlayerHasNoVisibleGames(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, bronze, silver, gold, platinum) VALUES\n" .
            "('NPWR010', 2, 2, 1, 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status) VALUES ('NPWR010', 1)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_player (np_communication_id, account_id, progress, bronze, silver, gold, platinum) VALUES\n" .
            "('NPWR010', 42, 12, 1, 0, 0, 0)"
        );

        $summary = $this->service->getSummary(99);

        $this->assertSame(0, $summary->getNumberOfGames());
        $this->assertSame(0, $summary->getNumberOfCompletedGames());
        $this->assertSame(null, $summary->getAverageProgress());
        $this->assertSame(0, $summary->getUnearnedTrophies());
    }

    public function testGetSummaryDoesNotAllowNegativeUnearnedTrophies(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, bronze, silver, gold, platinum) VALUES\n" .
            "('NPWR020', 1, 0, 0, 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_meta (np_communication_id, status) VALUES ('NPWR020', 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_player (np_communication_id, account_id, progress, bronze, silver, gold, platinum) VALUES\n" .
            "('NPWR020', 42, 100, 5, 0, 0, 0)"
        );

        $summary = $this->service->getSummary(42);

        $this->assertSame(0, $summary->getUnearnedTrophies());
    }
}
