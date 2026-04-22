<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Utility.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerRandomGame.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerRandomGamesFilter.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerRandomGamesService.php';

use Random\Engine\Mt19937;
use Random\Randomizer;

final class PlayerRandomGamesServiceTest extends TestCase
{
    private PDO $database;

    private PlayerRandomGamesService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            <<<SQL
            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL,
                name TEXT NOT NULL,
                icon_url TEXT,
                platform TEXT,
                platinum INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                bronze INTEGER NOT NULL DEFAULT 0
            )
            SQL
        );

        $this->database->exec(
            <<<SQL
            CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL,
                owners INTEGER NOT NULL DEFAULT 0,
                difficulty TEXT,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0
            )
            SQL
        );

        $this->database->exec(
            <<<SQL
            CREATE TABLE trophy_title_player (
                account_id INTEGER NOT NULL,
                np_communication_id TEXT NOT NULL,
                progress INTEGER
            )
            SQL
        );

        $this->service = new PlayerRandomGamesService(
            $this->database,
            new Utility(),
            new Randomizer(new Mt19937(1234))
        );
    }

    public function testFallbackExcludesAlreadySeenIds(): void
    {
        $this->insertEligibleGames(total: 40, platform: 'PS4');

        $rows = $this->invokeFetchFallbackGames(
            accountId: 55,
            filter: PlayerRandomGamesFilter::fromArray([]),
            limit: 8,
            seenIds: [1, 2, 3, 4, 5, 6]
        );

        $this->assertCount(8, $rows);
        foreach ($rows as $row) {
            $this->assertTrue(!in_array((int) $row['id'], [1, 2, 3, 4, 5, 6], true));
        }
    }

    public function testFallbackSelectionVariesAcrossRepeatedRunsWithLargePool(): void
    {
        $this->insertEligibleGames(total: 80, platform: 'PS5');

        $selectedIds = [];
        for ($i = 0; $i < 25; $i++) {
            $rows = $this->invokeFetchFallbackGames(
                accountId: 88,
                filter: PlayerRandomGamesFilter::fromArray([]),
                limit: 1,
                seenIds: [1, 2, 3, 4, 5]
            );

            $this->assertCount(1, $rows);
            $selectedIds[] = (int) $rows[0]['id'];
        }

        $this->assertTrue(
            count(array_unique($selectedIds)) > 1,
            'Expected fallback selection to vary across repeated runs when many eligible rows exist.'
        );
    }

    public function testPs3FallbackCanStillReturnEligibleRowsWhenRandomIdSamplingUnderfills(): void
    {
        $this->insertEligibleGames(total: 12, platform: 'PS3');
        $this->insertEligibleGames(total: 50, platform: 'PS5', startingId: 1000);

        $rows = $this->invokeFetchFallbackGames(
            accountId: 77,
            filter: PlayerRandomGamesFilter::fromArray([PlayerRandomGamesFilter::PLATFORM_PS3 => '1']),
            limit: 30,
            seenIds: [1, 2]
        );

        $this->assertCount(10, $rows);
        foreach ($rows as $row) {
            $this->assertStringContainsString('PS3', (string) $row['platform']);
            $this->assertTrue(!in_array((int) $row['id'], [1, 2], true));
        }
    }

    public function testGetRandomGamesSamplesFromSparsePs3EligibleSet(): void
    {
        $this->insertEligibleGames(total: 7, platform: 'PS3', startingId: 10);
        $this->insertEligibleGames(total: 120, platform: 'PS5', startingId: 1000);

        $games = $this->service->getRandomGames(
            accountId: 222,
            filter: PlayerRandomGamesFilter::fromArray([PlayerRandomGamesFilter::PLATFORM_PS3 => '1']),
            limit: 5
        );

        $this->assertCount(5, $games);
        foreach ($games as $game) {
            $this->assertTrue(in_array('PS3', $game->getPlatforms(), true));
        }
    }

    public function testGetRandomGamesPsvrFilterExcludesPsvr2Rows(): void
    {
        $this->insertEligibleGames(total: 8, platform: 'PSVR', startingId: 200);
        $this->insertEligibleGames(total: 12, platform: 'PSVR2', startingId: 400);

        $games = $this->service->getRandomGames(
            accountId: 333,
            filter: PlayerRandomGamesFilter::fromArray([PlayerRandomGamesFilter::PLATFORM_PSVR => '1']),
            limit: 6
        );

        $this->assertCount(6, $games);
        foreach ($games as $game) {
            $this->assertSame(['PSVR'], $game->getPlatforms());
        }
    }

    public function testGetRandomGamesHasNearUniformVariabilityAcrossRepeatedSampling(): void
    {
        $this->insertEligibleGames(total: 10, platform: 'PS5');

        $selectionCounts = [];
        for ($seed = 1; $seed <= 1000; $seed++) {
            $service = $this->createServiceWithSeed($seed);
            $games = $service->getRandomGames(
                accountId: 444,
                filter: PlayerRandomGamesFilter::fromArray([]),
                limit: 1
            );

            $this->assertCount(1, $games);
            $id = $games[0]->getId();
            $selectionCounts[$id] = ($selectionCounts[$id] ?? 0) + 1;
        }

        $this->assertCount(10, $selectionCounts);
        $this->assertTrue((max($selectionCounts) - min($selectionCounts)) <= 80);
    }

    private function insertEligibleGames(int $total, string $platform, int $startingId = 1): void
    {
        $titleStatement = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, name, icon_url, platform, platinum, gold, silver, bronze) '
            . 'VALUES (:id, :np, :name, :icon_url, :platform, 1, 2, 3, 4)'
        );

        $metaStatement = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, status, owners, difficulty, rarity_points, in_game_rarity_points) '
            . 'VALUES (:np, 0, 100, "3.50", 77, 33)'
        );

        for ($offset = 0; $offset < $total; $offset++) {
            $id = $startingId + $offset;
            $npCommunicationId = sprintf('NPWR%05d', $id);

            $titleStatement->bindValue(':id', $id, PDO::PARAM_INT);
            $titleStatement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
            $titleStatement->bindValue(':name', 'Game ' . $id, PDO::PARAM_STR);
            $titleStatement->bindValue(':icon_url', 'icon.png', PDO::PARAM_STR);
            $titleStatement->bindValue(':platform', $platform, PDO::PARAM_STR);
            $titleStatement->execute();

            $metaStatement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
            $metaStatement->execute();
        }
    }

    private function createServiceWithSeed(int $seed): PlayerRandomGamesService
    {
        return new PlayerRandomGamesService(
            $this->database,
            new Utility(),
            new Randomizer(new Mt19937($seed))
        );
    }

    /**
     * @param list<int> $seenIds
     * @return array<int, array<string, mixed>>
     */
    private function invokeFetchFallbackGames(int $accountId, PlayerRandomGamesFilter $filter, int $limit, array $seenIds): array
    {
        $reflectionMethod = new ReflectionMethod(PlayerRandomGamesService::class, 'fetchFallbackGames');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($this->service, $accountId, $filter, $limit, $seenIds);

        $this->assertTrue(is_array($result));

        return $result;
    }
}
