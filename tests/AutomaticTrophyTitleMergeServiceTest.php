<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/AutomaticTrophyTitleMergeService.php';

final class AutomaticTrophyTitleMergeServiceTest extends TestCase
{
    private PDO $database;

    /** @var RecordingTrophyMergeService */
    private RecordingTrophyMergeService $mergeService;

    private AutomaticTrophyTitleMergeService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createTables();

        $this->mergeService = new RecordingTrophyMergeService($this->database);
        $this->service = new AutomaticTrophyTitleMergeService($this->database, $this->mergeService);
    }

    public function testMergesIntoExistingCloneAndCopiesPs5Data(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS5');
        $this->insertTitle(2, 'MERGE_000001', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);
        $this->insertTrophies('MERGE_000001', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([
            ['NP_NEW', 'MERGE_000001'],
        ], $this->mergeService->copiedGames);

        $this->assertSame([
            [1, 2, 'order'],
        ], $this->mergeService->mergedGames);
    }

    public function testSkipsMergeWhenNameMappingAmbiguous(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS5');
        $this->insertTitle(2, 'MERGE_000001', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Hidden Trophy', 'Secret'],
            [1, 'Hidden Trophy', 'Secret'],
        ]);

        $this->insertTrophies('MERGE_000001', 'default', [
            [0, 'Hidden Trophy', 'Secret'],
        ]);

        $this->insertTrophies('MERGE_000001', 'dlc', [
            [0, 'Hidden Trophy', 'Secret'],
        ]);

        $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([], $this->mergeService->mergedGames);
    }

    public function testMergesIntoExistingCloneWithoutCopyWhenNotPs5(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS4');
        $this->insertTitle(2, 'MERGE_000001', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Trophy A', 'Detail A'],
        ]);
        $this->insertTrophies('MERGE_000001', 'default', [
            [0, 'Trophy A', 'Detail A'],
        ]);

        $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([], $this->mergeService->copiedGames);
        $this->assertSame([
            [1, 2, 'order'],
        ], $this->mergeService->mergedGames);
    }

    public function testClearsTrophyCacheAfterCopy(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS5');
        $this->insertTitle(2, 'MERGE_000001', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->insertTrophies('MERGE_000001', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([
            ['NP_NEW', 'MERGE_000001'],
        ], $this->mergeService->copiedGames);

        $cache = $this->getTrophyCache();

        $this->assertFalse(array_key_exists('NP_NEW', $cache));
        $this->assertFalse(array_key_exists('MERGE_000001', $cache));
    }

    public function testClonesPreferredPs5GameWhenNoCloneExists(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS5');
        $this->insertTitle(2, 'NP_OLD', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);
        $this->insertTrophies('NP_OLD', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->mergeService->cloneInfoQueue[] = [
            'clone_game_id' => 99,
            'clone_np_communication_id' => 'MERGE_000099',
        ];

        $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([1], $this->mergeService->clonedGames);
        $this->assertSame([
            [1, 99, 'order'],
            [2, 99, 'order'],
        ], $this->mergeService->mergedGames);
    }

    public function testSkipsCloningMergeNpCommunicationId(): void
    {
        $this->insertTitle(1, 'MERGE_000010', 'Example Game', 'PS5');
        $this->insertTitle(2, 'NP_OLD', 'Example Game', 'PS4');

        $this->insertTrophies('MERGE_000010', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->insertTrophies('NP_OLD', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->mergeService->cloneInfoQueue[] = [
            'clone_game_id' => 99,
            'clone_np_communication_id' => 'MERGE_000099',
        ];

        $this->service->handleNewTitle('MERGE_000010');

        $this->assertSame([2], $this->mergeService->clonedGames);
        $this->assertSame([
            [1, 99, 'order'],
            [2, 99, 'order'],
        ], $this->mergeService->mergedGames);
    }

    public function testSkipsPs5GameWithMergedStatus(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS4');
        $this->insertTitle(2, 'NP_PS5', 'Example Game', 'PS5', 2);

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->insertTrophies('NP_PS5', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $this->mergeService->cloneInfoQueue[] = [
            'clone_game_id' => 99,
            'clone_np_communication_id' => 'MERGE_000099',
        ];

        $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([1], $this->mergeService->clonedGames);
        $this->assertSame([
            [1, 99, 'order'],
        ], $this->mergeService->mergedGames);
    }

    public function testDoesNothingWhenNoMatchingTrophies(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS5');
        $this->insertTitle(2, 'NP_OLD', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Trophy A', 'Detail A'],
        ]);
        $this->insertTrophies('NP_OLD', 'default', [
            [0, 'Different Trophy', 'Different Detail'],
        ]);

        $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([], $this->mergeService->clonedGames);
        $this->assertSame([], $this->mergeService->mergedGames);
        $this->assertSame([], $this->mergeService->copiedGames);
    }

    private function createTables(): void
    {
        $this->database->exec(
            'CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                platform TEXT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                detail TEXT NULL
            )'
        );
    }

    private function insertTitle(int $id, string $npCommunicationId, string $name, string $platform, int $status = 0): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id, name, platform) VALUES (:id, :np, :name, :platform)'
        );
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':name', $name, PDO::PARAM_STR);
        $query->bindValue(':platform', $platform, PDO::PARAM_STR);
        $query->execute();

        $metaQuery = $this->database->prepare(
            'INSERT INTO trophy_title_meta (np_communication_id, status) VALUES (:np, :status)'
        );
        $metaQuery->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $metaQuery->bindValue(':status', $status, PDO::PARAM_INT);
        $metaQuery->execute();
    }

    /**
     * @param array<int, array{0:int, 1:string, 2:string}> $trophies
     */
    private function insertTrophies(string $npCommunicationId, string $groupId, array $trophies): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy (np_communication_id, group_id, order_id, name, detail) VALUES (:np, :group, :order, :name, :detail)'
        );

        foreach ($trophies as [$order, $name, $detail]) {
            $query->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
            $query->bindValue(':group', $groupId, PDO::PARAM_STR);
            $query->bindValue(':order', $order, PDO::PARAM_INT);
            $query->bindValue(':name', $name, PDO::PARAM_STR);
            $query->bindValue(':detail', $detail, PDO::PARAM_STR);
            $query->execute();
        }
    }

    /**
     * @return array<string, list<array{group_id:string, order_id:int, name:string, detail:string}>>
     */
    private function getTrophyCache(): array
    {
        $reflection = new ReflectionObject($this->service);
        $property = $reflection->getProperty('trophyCache');
        $property->setAccessible(true);

        /** @var array<string, list<array{group_id:string, order_id:int, name:string, detail:string}>> $cache */
        $cache = $property->getValue($this->service);

        return $cache;
    }
}

final class RecordingTrophyMergeService extends TrophyMergeService
{
    /** @var list<array{0:string,1:string}> */
    public array $copiedGames = [];

    /** @var list<array{0:int,1:int,2:string}> */
    public array $mergedGames = [];

    /** @var list<int> */
    public array $clonedGames = [];

    /** @var list<array{clone_game_id:int, clone_np_communication_id:string}> */
    public array $cloneInfoQueue = [];

    public function __construct(PDO $database)
    {
        parent::__construct($database);
    }

    public function copyGameData(string $sourceNpCommunicationId, string $targetNpCommunicationId): void
    {
        $this->copiedGames[] = [$sourceNpCommunicationId, $targetNpCommunicationId];
    }

    public function mergeGames(int $childGameId, int $parentGameId, string $method, ?TrophyMergeProgressListener $progressListener = null): string
    {
        $this->mergedGames[] = [$childGameId, $parentGameId, $method];

        return 'The games have been merged.';
    }

    public function cloneGameWithInfo(int $childGameId): array
    {
        $this->clonedGames[] = $childGameId;

        if (empty($this->cloneInfoQueue)) {
            throw new RuntimeException('No clone info configured.');
        }

        return array_shift($this->cloneInfoQueue);
    }
}
