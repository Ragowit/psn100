<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameRecentPlayersQueryBuilder.php';
require_once __DIR__ . '/../wwwroot/classes/GamePlayerFilter.php';

final class GameRecentPlayersQueryBuilderTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();
        $this->seedData();
    }

    public function testPrepareLimitsAndOrdersResults(): void
    {
        $filter = new GamePlayerFilter(null, null);
        $queryBuilder = new GameRecentPlayersQueryBuilder($filter, 2);
        $statement = $queryBuilder->prepare($this->database, 'NPWR12345_001');
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(2, $rows);
        $this->assertSame([1, 2], array_map('intval', array_column($rows, 'account_id')));
        $this->assertSame(['2024-01-03', '2024-01-02'], array_column($rows, 'last_known_date'));
    }


    public function testPrepareNormalizesLimitToAtLeastOne(): void
    {
        $filter = new GamePlayerFilter(null, null);
        $queryBuilder = new GameRecentPlayersQueryBuilder($filter, 0);
        $statement = $queryBuilder->prepare($this->database, 'NPWR12345_001');
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertSame([1], array_map('intval', array_column($rows, 'account_id')));
    }

    public function testPrepareAppliesCountryFilter(): void
    {
        $filter = new GamePlayerFilter('US', null);
        $queryBuilder = new GameRecentPlayersQueryBuilder($filter, 10);
        $statement = $queryBuilder->prepare($this->database, 'NPWR12345_001');
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([1, 3], array_map('intval', array_column($rows, 'account_id')));
    }

    public function testPrepareAppliesAvatarFilter(): void
    {
        $filter = new GamePlayerFilter(null, 'avatar-2.png');
        $queryBuilder = new GameRecentPlayersQueryBuilder($filter, 10);
        $statement = $queryBuilder->prepare($this->database, 'NPWR12345_001');
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([2, 3], array_map('intval', array_column($rows, 'account_id')));
    }

    public function testPrepareAppliesCountryAndAvatarFilter(): void
    {
        $filter = new GamePlayerFilter('US', 'avatar-2.png');
        $queryBuilder = new GameRecentPlayersQueryBuilder($filter, 10);
        $statement = $queryBuilder->prepare($this->database, 'NPWR12345_001');
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertSame([3], array_map('intval', array_column($rows, 'account_id')));
    }

    private function createTables(): void
    {
        $this->database->exec(
            'CREATE TABLE player (
                account_id INTEGER PRIMARY KEY,
                avatar_url TEXT NOT NULL,
                country TEXT,
                online_id TEXT NOT NULL,
                status INTEGER NOT NULL,
                trophy_count_npwr INTEGER NOT NULL,
                trophy_count_sony INTEGER NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE player_ranking (
                account_id INTEGER PRIMARY KEY,
                ranking INTEGER NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_player (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                bronze INTEGER NOT NULL,
                silver INTEGER NOT NULL,
                gold INTEGER NOT NULL,
                platinum INTEGER NOT NULL,
                progress REAL NOT NULL,
                last_updated_date TEXT NOT NULL,
                trophy_count_npwr INTEGER NOT NULL,
                trophy_count_sony INTEGER NOT NULL,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_common INTEGER NOT NULL DEFAULT 0,
                in_game_uncommon INTEGER NOT NULL DEFAULT 0,
                in_game_rare INTEGER NOT NULL DEFAULT 0,
                in_game_epic INTEGER NOT NULL DEFAULT 0,
                in_game_legendary INTEGER NOT NULL DEFAULT 0
            )'
        );
    }

    private function seedData(): void
    {
        $playerStatement = $this->database->prepare(
            'INSERT INTO player (account_id, avatar_url, country, online_id, status, trophy_count_npwr, trophy_count_sony)
             VALUES (:account_id, :avatar_url, :country, :online_id, :status, :trophy_count_npwr, :trophy_count_sony)'
        );

        $rankingStatement = $this->database->prepare(
            'INSERT INTO player_ranking (account_id, ranking) VALUES (:account_id, :ranking)'
        );

        $trophyStatement = $this->database->prepare(
            'INSERT INTO trophy_title_player (
                account_id,
                np_communication_id,
                bronze,
                silver,
                gold,
                platinum,
                progress,
                last_updated_date,
                trophy_count_npwr,
                trophy_count_sony
            ) VALUES (
                :account_id,
                :np_communication_id,
                :bronze,
                :silver,
                :gold,
                :platinum,
                :progress,
                :last_updated_date,
                :trophy_count_npwr,
                :trophy_count_sony
            )'
        );

        $players = [
            ['id' => 1, 'avatar' => 'avatar-1.png', 'country' => 'US', 'online_id' => 'PlayerOne', 'status' => 0, 'ranking' => 100, 'last_updated' => '2024-01-03', 'np' => 'NPWR12345_001'],
            ['id' => 2, 'avatar' => 'avatar-2.png', 'country' => 'GB', 'online_id' => 'PlayerTwo', 'status' => 0, 'ranking' => 200, 'last_updated' => '2024-01-02', 'np' => 'NPWR12345_001'],
            ['id' => 3, 'avatar' => 'avatar-2.png', 'country' => 'US', 'online_id' => 'PlayerThree', 'status' => 0, 'ranking' => 300, 'last_updated' => '2024-01-01', 'np' => 'NPWR12345_001'],
            ['id' => 4, 'avatar' => 'avatar-3.png', 'country' => 'US', 'online_id' => 'PlayerFour', 'status' => 1, 'ranking' => 400, 'last_updated' => '2024-01-04', 'np' => 'NPWR12345_001'],
            ['id' => 5, 'avatar' => 'avatar-4.png', 'country' => 'US', 'online_id' => 'PlayerFive', 'status' => 0, 'ranking' => 15001, 'last_updated' => '2024-01-05', 'np' => 'NPWR12345_001'],
            ['id' => 6, 'avatar' => 'avatar-5.png', 'country' => 'US', 'online_id' => 'PlayerSix', 'status' => 0, 'ranking' => 500, 'last_updated' => '2024-01-06', 'np' => 'NPWR00000_002'],
        ];

        foreach ($players as $player) {
            $playerStatement->execute([
                ':account_id' => $player['id'],
                ':avatar_url' => $player['avatar'],
                ':country' => $player['country'],
                ':online_id' => $player['online_id'],
                ':status' => $player['status'],
                ':trophy_count_npwr' => 100,
                ':trophy_count_sony' => 200,
            ]);

            $rankingStatement->execute([
                ':account_id' => $player['id'],
                ':ranking' => $player['ranking'],
            ]);

            $trophyStatement->execute([
                ':account_id' => $player['id'],
                ':np_communication_id' => $player['np'],
                ':bronze' => 1,
                ':silver' => 2,
                ':gold' => 3,
                ':platinum' => 4,
                ':progress' => 50,
                ':last_updated_date' => $player['last_updated'],
                ':trophy_count_npwr' => 10,
                ':trophy_count_sony' => 20,
            ]);
        }
    }
}
