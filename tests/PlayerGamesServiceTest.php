<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerGamesService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerGamesFilter.php';
require_once __DIR__ . '/../wwwroot/classes/SearchQueryHelper.php';

final class PlayerGamesServiceTest extends TestCase
{
    private PDO $pdo;

    private PlayerGamesService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL,
                name TEXT NOT NULL,
                icon_url TEXT,
                platform TEXT
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER,
                rarity_points INTEGER,
                obsolete_ids TEXT,
                psnprofiles_id TEXT NULL
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title_player (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                bronze INTEGER,
                silver INTEGER,
                gold INTEGER,
                platinum INTEGER,
                progress INTEGER,
                last_updated_date TEXT,
                rarity_points INTEGER
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_group_player (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                progress INTEGER
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_earned (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                earned_date TEXT,
                earned INTEGER
            )
            SQL
        );

        $this->service = new PlayerGamesService($this->pdo, new SearchQueryHelper());
    }

    public function testCountPlayerGamesAppliesFilters(): void
    {
        $this->insertGame(
            id: 1,
            npCommunicationId: 'NPWR001',
            name: 'Alpha Game',
            platform: 'PS4, PS5',
            status: 0,
            accountId: 42,
            progress: 100,
            baseProgress: 100
        );

        $this->insertGame(
            id: 2,
            npCommunicationId: 'NPWR002',
            name: 'Beta Game',
            platform: 'PS4',
            status: 2,
            accountId: 42,
            progress: 100,
            baseProgress: 100
        );

        $this->insertGame(
            id: 3,
            npCommunicationId: 'NPWR003',
            name: 'Gamma Game',
            platform: 'PS5',
            status: 0,
            accountId: 42,
            progress: 100,
            baseProgress: 50
        );

        $filter = PlayerGamesFilter::fromArray([
            'completed' => '1',
            'base' => '1',
            PlayerGamesFilter::PLATFORM_PS4 => '1',
        ]);

        $count = $this->service->countPlayerGames(42, $filter);

        $this->assertSame(1, $count);
    }

    public function testGetPlayerGamesHydratesResultsAndCompletionLabel(): void
    {
        $this->insertGame(
            id: 10,
            npCommunicationId: 'NPWR777',
            name: 'Delta Game',
            platform: 'PS4',
            status: 0,
            accountId: 7,
            progress: 100,
            baseProgress: 100,
            bronze: 1,
            silver: 2,
            gold: 3,
            platinum: 1,
            lastUpdatedDate: '2024-03-10 12:34:56',
            rarityPoints: 321,
            maxRarityPoints: 654
        );

        $this->pdo->exec(
            "INSERT INTO trophy_earned (account_id, np_communication_id, earned_date, earned) VALUES " .
            " (7, 'NPWR777', '2024-03-01 10:00:00', 1)," .
            " (7, 'NPWR777', '2024-03-05 11:12:13', 1)"
        );

        $filter = PlayerGamesFilter::fromArray([]);

        $games = $this->service->getPlayerGames(7, $filter);

        $this->assertCount(1, $games);

        $game = $games[0];
        $this->assertSame('Delta Game', $game->getName());
        $this->assertSame('NPWR777', $game->getNpCommunicationId());
        $this->assertSame(1, $game->getBronze());
        $this->assertSame(2, $game->getSilver());
        $this->assertSame(3, $game->getGold());
        $this->assertSame(1, $game->getPlatinum());
        $this->assertSame(100, $game->getProgress());
        $this->assertSame('2024-03-10 12:34:56', $game->getLastUpdatedDate());
        $this->assertSame(321, $game->getRarityPoints());
        $this->assertSame(654, $game->getMaxRarityPoints());
        $this->assertSame('Completed in 4 days, 1 hours', $game->getCompletionDurationLabel());
    }

    private function insertGame(
        int $id,
        string $npCommunicationId,
        string $name,
        string $platform,
        int $status,
        int $accountId,
        int $progress,
        int $baseProgress,
        int $bronze = 0,
        int $silver = 0,
        int $gold = 0,
        int $platinum = 0,
        string $lastUpdatedDate = '2024-01-01 00:00:00',
        int $rarityPoints = 0,
        int $maxRarityPoints = 0
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, name, icon_url, platform) '
            . 'VALUES (:id, :np, :name, :icon, :platform)'
        );
        $statement->execute([
            ':id' => $id,
            ':np' => $npCommunicationId,
            ':name' => $name,
            ':icon' => 'icon.png',
            ':platform' => $platform,
        ]);

        $statement = $this->pdo->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, status, rarity_points) '
            . 'VALUES (:np, :status, :rarity)'
        );
        $statement->execute([
            ':np' => $npCommunicationId,
            ':status' => $status,
            ':rarity' => $maxRarityPoints,
        ]);

        $statement = $this->pdo->prepare(
            'INSERT INTO trophy_title_player (account_id, np_communication_id, bronze, silver, gold, platinum, progress, last_updated_date, rarity_points) '
            . 'VALUES (:account_id, :np, :bronze, :silver, :gold, :platinum, :progress, :last_updated_date, :rarity_points)'
        );
        $statement->execute([
            ':account_id' => $accountId,
            ':np' => $npCommunicationId,
            ':bronze' => $bronze,
            ':silver' => $silver,
            ':gold' => $gold,
            ':platinum' => $platinum,
            ':progress' => $progress,
            ':last_updated_date' => $lastUpdatedDate,
            ':rarity_points' => $rarityPoints,
        ]);

        $statement = $this->pdo->prepare(
            'INSERT INTO trophy_group_player (account_id, np_communication_id, group_id, progress) '
            . 'VALUES (:account_id, :np, :group_id, :progress)'
        );
        $statement->execute([
            ':account_id' => $accountId,
            ':np' => $npCommunicationId,
            ':group_id' => 'default',
            ':progress' => $baseProgress,
        ]);
    }
}
