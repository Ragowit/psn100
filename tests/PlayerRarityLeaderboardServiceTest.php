<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerRarityLeaderboardService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerLeaderboardFilter.php';

final class PlayerRarityLeaderboardServiceTest extends TestCase
{
    private PDO $pdo;
    private PlayerRarityLeaderboardService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema();
        $this->seedPlayers();

        $this->service = new PlayerRarityLeaderboardService($this->pdo);
    }

    public function testCountPlayersAppliesCountryAndAvatarFilters(): void
    {
        $filter = new PlayerLeaderboardFilter('US', 'cat', 1);
        $this->assertSame(1, $this->service->countPlayers($filter));

        $countryOnlyFilter = new PlayerLeaderboardFilter('US', null, 1);
        $this->assertSame(2, $this->service->countPlayers($countryOnlyFilter));

        $avatarOnlyFilter = new PlayerLeaderboardFilter(null, 'fox', 1);
        $this->assertSame(2, $this->service->countPlayers($avatarOnlyFilter));
    }

    public function testGetPlayersReturnsOrderedResultsAndHonorsPagination(): void
    {
        $filter = new PlayerLeaderboardFilter(null, null, 1);
        $firstPage = $this->service->getPlayers($filter, 2);

        $this->assertCount(2, $firstPage);
        $this->assertSame('2', $firstPage[0]['account_id']);
        $this->assertSame(1, (int) $firstPage[0]['ranking']);
        $this->assertSame(1, (int) $firstPage[0]['ranking_country']);
        $this->assertSame('1', $firstPage[1]['account_id']);
        $this->assertSame(2, (int) $firstPage[1]['ranking']);

        $secondPage = $this->service->getPlayers(new PlayerLeaderboardFilter(null, null, 2), 2);
        $this->assertCount(1, $secondPage);
        $this->assertSame('3', $secondPage[0]['account_id']);
        $this->assertSame(3, (int) $secondPage[0]['ranking']);
    }

    public function testGetPageSizeReturnsConfiguredPageSize(): void
    {
        $this->assertSame(50, $this->service->getPageSize());
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            'CREATE TABLE player (
                account_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL,
                country TEXT,
                avatar_url TEXT
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE player_ranking (
                account_id TEXT PRIMARY KEY,
                rarity_ranking INTEGER NOT NULL,
                rarity_ranking_country INTEGER
            )'
        );
    }

    private function seedPlayers(): void
    {
        $players = [
            ['account_id' => '1', 'status' => 0, 'country' => 'US', 'avatar_url' => 'cat', 'ranking' => 2, 'ranking_country' => 2],
            ['account_id' => '2', 'status' => 0, 'country' => 'CA', 'avatar_url' => 'fox', 'ranking' => 1, 'ranking_country' => 1],
            ['account_id' => '3', 'status' => 0, 'country' => 'US', 'avatar_url' => 'fox', 'ranking' => 3, 'ranking_country' => 3],
            ['account_id' => '4', 'status' => 1, 'country' => 'US', 'avatar_url' => 'cat', 'ranking' => 4, 'ranking_country' => 4],
        ];

        $playerStatement = $this->pdo->prepare(
            'INSERT INTO player (account_id, status, country, avatar_url) VALUES (:account_id, :status, :country, :avatar_url)'
        );
        $rankingStatement = $this->pdo->prepare(
            'INSERT INTO player_ranking (account_id, rarity_ranking, rarity_ranking_country) VALUES (:account_id, :rarity_ranking, :rarity_ranking_country)'
        );

        foreach ($players as $player) {
            $playerStatement->execute([
                ':account_id' => $player['account_id'],
                ':status' => $player['status'],
                ':country' => $player['country'],
                ':avatar_url' => $player['avatar_url'],
            ]);

            $rankingStatement->execute([
                ':account_id' => $player['account_id'],
                ':rarity_ranking' => $player['ranking'],
                ':rarity_ranking_country' => $player['ranking_country'],
            ]);
        }
    }
}
