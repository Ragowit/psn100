<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanQueueSelector.php';

final class PlayerScanQueueSelectorTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, scanning TEXT)');
        $this->database->exec('CREATE TABLE player_queue (online_id TEXT PRIMARY KEY, request_time TEXT NOT NULL)');
        $this->database->exec(
            'CREATE TABLE player (
                account_id INTEGER PRIMARY KEY,
                online_id TEXT NOT NULL,
                last_updated_date TEXT,
                status INTEGER NOT NULL DEFAULT 99
            )'
        );
        $this->database->exec(
            'CREATE TABLE player_ranking (
                account_id INTEGER PRIMARY KEY,
                ranking INTEGER,
                rarity_ranking INTEGER,
                in_game_rarity_ranking INTEGER
            )'
        );
        $this->database->exec('INSERT INTO setting (id, scanning) VALUES (1, NULL), (2, NULL)');
    }

    public function testSelectNextCandidateReturnsFalseWhenQueueIsEmpty(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();

        $result = $selector->selectNextCandidate(1);

        $this->assertSame(false, $result);
    }

    public function testSelectNextCandidatePrefersTierOneQueueEntries(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();
        $this->insertPlayer(100, 'queued-user', '2024-06-15 11:00:00', 99);
        $this->insertRanking(100, 50, 50, 50);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('queued-user', '2024-06-15 11:30:00')"
        );

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('queued-user', $result['online_id']);
        $this->assertSame(100, (int) $result['account_id']);
    }

    public function testSelectNextCandidatePrefersQueueOverStaleRankedPlayers(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();
        $this->insertPlayer(100, 'queued-user', '2024-06-15 10:00:00', 99);
        $this->insertPlayer(200, 'top-player', '2024-06-15 10:00:00', 99);
        $this->insertRanking(200, 10, 10, 10);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('queued-user', '2024-06-15 11:00:00')"
        );

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('queued-user', $result['online_id']);
    }

    public function testSelectNextCandidateSkipsPlayersBeingScannedByAnotherWorker(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();
        $this->database->exec("UPDATE setting SET scanning = 'busy-user' WHERE id = 2");
        $this->insertPlayer(300, 'busy-user', '2024-06-15 10:00:00', 99);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('busy-user', '2024-06-15 11:00:00')"
        );
        $this->insertPlayer(400, 'available-user', '2024-06-15 10:00:00', 99);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('available-user', '2024-06-15 11:30:00')"
        );

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('available-user', $result['online_id']);
    }

    public function testSelectNextCandidateSelectsStaleTopHundredPlayerForTierTwo(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();
        $this->insertPlayer(500, 'top-hundred', '2024-06-15 10:00:00', 99);
        $this->insertRanking(500, 75, 5000, 5000);

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('top-hundred', $result['online_id']);
    }

    public function testSelectNextCandidateFallsBackToTierNineForAnyRankedPlayer(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();
        $this->insertPlayer(600, 'fresh-top-hundred', '2024-06-15 11:30:00', 99);
        $this->insertRanking(600, 20, 20, 20);

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('fresh-top-hundred', $result['online_id']);
    }

    public function testSelectNextCandidateSelectsNotFoundPlayersForTierFive(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();
        $this->insertPlayer(700, 'missing-player', '2024-06-14 10:00:00', 5);

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('missing-player', $result['online_id']);
    }

    public function testSelectNextCandidateOrdersQueueEntriesByRequestTime(): void
    {
        $selector = $this->createSelectorWithDefaultCutoffs();
        $this->insertPlayer(801, 'older-queue', '2024-06-15 10:00:00', 99);
        $this->insertPlayer(802, 'newer-queue', '2024-06-15 10:00:00', 99);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES
                ('newer-queue', '2024-06-15 11:30:00'),
                ('older-queue', '2024-06-15 11:00:00')"
        );

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('older-queue', $result['online_id']);
    }

    public function testSelectNextCandidateUsesMysqlCompatibleOneMonthCutoffOnMonthEndDates(): void
    {
        $selector = $this->createSelector(
            '2026-03-31 12:00:00',
            '2026-03-31 11:00:00',
            '2026-03-30 12:00:00',
            '2026-03-24 12:00:00',
            '2026-02-28 12:00:00',
            '2025-12-31 12:00:00',
        );
        $this->insertPlayer(900, 'too-recent-private', '2026-03-02 10:00:00', 3);
        $this->insertPlayer(901, 'stale-private', '2026-02-27 10:00:00', 3);

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('stale-private', $result['online_id']);
    }

    public function testSelectNextCandidateUsesMysqlCompatibleThreeMonthCutoffOnMonthEndDates(): void
    {
        $selector = $this->createSelector(
            '2026-05-31 12:00:00',
            '2026-05-31 11:00:00',
            '2026-05-30 12:00:00',
            '2026-05-24 12:00:00',
            '2026-04-30 12:00:00',
            '2026-02-28 12:00:00',
        );
        $this->insertPlayer(910, 'too-recent-inactive', '2026-03-02 10:00:00', 4);
        $this->insertPlayer(911, 'stale-inactive', '2026-02-27 10:00:00', 4);

        $result = $selector->selectNextCandidate(1);

        $this->assertSame('stale-inactive', $result['online_id']);
    }

    private function createSelectorWithDefaultCutoffs(): PlayerScanQueueSelector
    {
        return $this->createSelector(
            '2024-06-15 12:00:00',
            '2024-06-15 11:00:00',
            '2024-06-14 12:00:00',
            '2024-06-08 12:00:00',
            '2024-05-15 12:00:00',
            '2024-03-15 12:00:00',
        );
    }

    private function createSelector(
        string $now,
        string $cutoff1Hour,
        string $cutoff1Day,
        string $cutoff1Week,
        string $cutoff1Month,
        string $cutoff3Months,
    ): PlayerScanQueueSelector {
        return new PlayerScanQueueSelector(
            $this->database,
            PlayerScanQueueSelector::selectionSqlWithLiteralCutoffs(
                $now,
                $cutoff1Hour,
                $cutoff1Day,
                $cutoff1Week,
                $cutoff1Month,
                $cutoff3Months,
            ),
        );
    }

    private function insertPlayer(int $accountId, string $onlineId, string $lastUpdatedDate, int $status): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO player (account_id, online_id, last_updated_date, status)
            VALUES (:account_id, :online_id, :last_updated_date, :status)'
        );
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $statement->bindValue(':last_updated_date', $lastUpdatedDate, PDO::PARAM_STR);
        $statement->bindValue(':status', $status, PDO::PARAM_INT);
        $statement->execute();
    }

    private function insertRanking(
        int $accountId,
        int $ranking,
        int $rarityRanking,
        int $inGameRarityRanking
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO player_ranking (account_id, ranking, rarity_ranking, in_game_rarity_ranking)
            VALUES (:account_id, :ranking, :rarity_ranking, :in_game_rarity_ranking)'
        );
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $statement->bindValue(':ranking', $ranking, PDO::PARAM_INT);
        $statement->bindValue(':rarity_ranking', $rarityRanking, PDO::PARAM_INT);
        $statement->bindValue(':in_game_rarity_ranking', $inGameRarityRanking, PDO::PARAM_INT);
        $statement->execute();
    }
}
