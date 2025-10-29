<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerRepository.php';

final class PlayerRepositoryTest extends TestCase
{
    private PDO $database;
    private PlayerRepository $repository;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->database->exec('CREATE TABLE player (
            account_id INTEGER PRIMARY KEY,
            online_id TEXT NOT NULL
        )');

        $this->database->exec('CREATE TABLE player_ranking (
            account_id INTEGER PRIMARY KEY,
            ranking INTEGER NOT NULL,
            ranking_country INTEGER NOT NULL,
            rarity_ranking INTEGER NOT NULL,
            rarity_ranking_country INTEGER NOT NULL
        )');

        $this->repository = new PlayerRepository($this->database);
    }

    public function testFindAccountIdByOnlineIdReturnsAccountIdWhenRecordExists(): void
    {
        $this->database->exec("INSERT INTO player (account_id, online_id) VALUES (101, 'Legolas')");

        $accountId = $this->repository->findAccountIdByOnlineId('Legolas');

        $this->assertSame(101, $accountId);
    }

    public function testFindAccountIdByOnlineIdReturnsNullWhenNoRecordMatches(): void
    {
        $accountId = $this->repository->findAccountIdByOnlineId('Unknown');

        $this->assertSame(null, $accountId);
    }

    public function testFetchPlayerByAccountIdReturnsPlayerWithRankingInformation(): void
    {
        $this->database->exec("INSERT INTO player (account_id, online_id) VALUES (42, 'Aloy')");
        $this->database->exec('INSERT INTO player_ranking (account_id, ranking, ranking_country, rarity_ranking, rarity_ranking_country)
            VALUES (42, 5, 10, 7, 14)
        ');

        $player = $this->repository->fetchPlayerByAccountId(42);

        $this->assertSame([
            'account_id' => 42,
            'online_id' => 'Aloy',
            'ranking' => 5,
            'rarity_ranking' => 7,
            'ranking_country' => 10,
            'rarity_ranking_country' => 14,
        ], $player);
    }

    public function testFetchPlayerByAccountIdReturnsNullWhenPlayerMissing(): void
    {
        $player = $this->repository->fetchPlayerByAccountId(999);

        $this->assertSame(null, $player);
    }

    public function testFetchPlayerByAccountIdReturnsPlayerWithNullRankingWhenNoRankingRow(): void
    {
        $this->database->exec("INSERT INTO player (account_id, online_id) VALUES (88, 'Jill')");

        $player = $this->repository->fetchPlayerByAccountId(88);

        $this->assertSame([
            'account_id' => 88,
            'online_id' => 'Jill',
            'ranking' => null,
            'rarity_ranking' => null,
            'ranking_country' => null,
            'rarity_ranking_country' => null,
        ], $player);
    }
}
