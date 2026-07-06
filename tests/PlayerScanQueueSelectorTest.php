<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanQueueSelector.php';

final class PlayerScanQueueSelectorTest extends TestCase
{
    private PDO $database;
    private PlayerScanQueueSelector $selector;
    private DateTimeImmutable $referenceTime;

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

        $this->selector = new PlayerScanQueueSelector($this->database);
        $this->referenceTime = new DateTimeImmutable('2024-06-15 12:00:00');
    }

    public function testSelectNextCandidateReturnsFalseWhenQueueIsEmpty(): void
    {
        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame(false, $result);
    }

    public function testSelectNextCandidatePrefersTierOneQueueEntries(): void
    {
        $this->insertPlayer(100, 'queued-user', '2024-06-15 11:00:00', 99);
        $this->insertRanking(100, 50, 50, 50);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('queued-user', '2024-06-15 11:30:00')"
        );

        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame('queued-user', $result['online_id']);
        $this->assertSame(100, (int) $result['account_id']);
    }

    public function testSelectNextCandidatePrefersQueueOverStaleRankedPlayers(): void
    {
        $this->insertPlayer(100, 'queued-user', '2024-06-15 10:00:00', 99);
        $this->insertPlayer(200, 'top-player', '2024-06-15 10:00:00', 99);
        $this->insertRanking(200, 10, 10, 10);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('queued-user', '2024-06-15 11:00:00')"
        );

        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame('queued-user', $result['online_id']);
    }

    public function testSelectNextCandidateSkipsPlayersBeingScannedByAnotherWorker(): void
    {
        $this->database->exec("UPDATE setting SET scanning = 'busy-user' WHERE id = 2");
        $this->insertPlayer(300, 'busy-user', '2024-06-15 10:00:00', 99);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('busy-user', '2024-06-15 11:00:00')"
        );
        $this->insertPlayer(400, 'available-user', '2024-06-15 10:00:00', 99);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES ('available-user', '2024-06-15 11:30:00')"
        );

        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame('available-user', $result['online_id']);
    }

    public function testSelectNextCandidateSelectsStaleTopHundredPlayerForTierTwo(): void
    {
        $this->insertPlayer(500, 'top-hundred', '2024-06-15 10:00:00', 99);
        $this->insertRanking(500, 75, 5000, 5000);

        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame('top-hundred', $result['online_id']);
    }

    public function testSelectNextCandidateFallsBackToTierNineForAnyRankedPlayer(): void
    {
        $this->insertPlayer(600, 'fresh-top-hundred', '2024-06-15 11:30:00', 99);
        $this->insertRanking(600, 20, 20, 20);

        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame('fresh-top-hundred', $result['online_id']);
    }

    public function testSelectNextCandidateSelectsNotFoundPlayersForTierFive(): void
    {
        $this->insertPlayer(700, 'missing-player', '2024-06-14 10:00:00', 5);

        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame('missing-player', $result['online_id']);
    }

    public function testSelectNextCandidateOrdersQueueEntriesByRequestTime(): void
    {
        $this->insertPlayer(801, 'older-queue', '2024-06-15 10:00:00', 99);
        $this->insertPlayer(802, 'newer-queue', '2024-06-15 10:00:00', 99);
        $this->database->exec(
            "INSERT INTO player_queue (online_id, request_time) VALUES
                ('newer-queue', '2024-06-15 11:30:00'),
                ('older-queue', '2024-06-15 11:00:00')"
        );

        $result = $this->selector->selectNextCandidate(1, $this->referenceTime);

        $this->assertSame('older-queue', $result['online_id']);
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
