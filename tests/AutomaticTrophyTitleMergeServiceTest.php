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

        $parentsToRecompute = $this->service->handleNewTitle('NP_NEW');

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

        $parentsToRecompute = $this->service->handleNewTitle('NP_NEW');

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

        $parentsToRecompute = $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([], $this->mergeService->copiedGames);
        $this->assertSame([
            [1, 2, 'order'],
        ], $this->mergeService->mergedGames);
    }

    public function testTrimsWhitespaceWhenComparingTrophies(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS4');
        $this->insertTitle(2, 'MERGE_000001', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, ' Trophy A ', ' Detail A '],
            [1, "Trophy B\t", 'Detail B '],
        ]);

        $this->insertTrophies('MERGE_000001', 'default', [
            [0, 'Trophy A', 'Detail A'],
            [1, 'Trophy B', 'Detail B'],
        ]);

        $parentsToRecompute = $this->service->handleNewTitle('NP_NEW');

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

        $parentsToRecompute = $this->service->handleNewTitle('NP_NEW');

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

        $parentsToRecompute = $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([1], $this->mergeService->clonedGames);
        $this->assertSame([
            [1, 99, 'order'],
            [2, 99, 'order'],
        ], $this->mergeService->mergedGames);
    }

    public function testRecomputesMergeProgressWhenAmbiguousMappingIsSkipped(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS5');
        $this->insertTitle(2, 'NP_MATCH', 'Example Game', 'PS4');
        $this->insertTitle(3, 'NP_AMBIG', 'Example Game', 'PS4');

        $this->insertTrophies('NP_NEW', 'default', [
            [0, 'Hidden Trophy', 'Alpha'],
            [1, 'Hidden Trophy', 'Beta'],
        ]);

        $this->insertTrophies('NP_MATCH', 'default', [
            [0, 'Hidden Trophy', 'Alpha'],
            [1, 'Hidden Trophy', 'Beta'],
        ]);

        $this->insertTrophies('NP_AMBIG', 'default', [
            [0, 'Hidden Trophy', 'Beta'],
            [1, 'Hidden Trophy', 'Alpha'],
        ]);

        $this->mergeService->cloneInfoQueue[] = [
            'clone_game_id' => 99,
            'clone_np_communication_id' => 'MERGE_000099',
        ];

        $parentsToRecompute = $this->service->handleNewTitle('NP_NEW');

        $this->assertSame([1], $this->mergeService->clonedGames);
        $this->assertSame([
            [1, 99, 'order'],
            [2, 99, 'order'],
        ], $this->mergeService->mergedGames);
        $this->assertSame(['MERGE_000099'], $parentsToRecompute);
    }

    public function testMergeTitleRecalculationUsesAllChildrenAfterSecondMerge(): void
    {
        $this->insertTitle(1, 'NP_CHILD_ONE', 'Example Game', 'PS4');
        $this->insertTitle(2, 'NP_CHILD_TWO', 'Example Game', 'PS5');
        $this->insertTitle(3, 'MERGE_000003', 'Example Game', 'PS4');

        $this->insertTrophyMerge('NP_CHILD_ONE', 'MERGE_000003');
        $this->insertTrophyTitlePlayer('NP_CHILD_ONE', 100, 1, 0, 0, 0, 10, 1000);

        $this->insertTrophyMerge('NP_CHILD_TWO', 'MERGE_000003');

        $method = new ReflectionMethod(TrophyMergeService::class, 'getMergeParentAndChildren');
        $method->setAccessible(true);

        /** @var array{parent_np_communication_id:string, child_np_communication_ids:list<string>} $result */
        $result = $method->invoke($this->mergeService, 'NP_CHILD_TWO');

        $this->assertSame('MERGE_000003', $result['parent_np_communication_id']);
        $this->assertSame(['NP_CHILD_ONE', 'NP_CHILD_TWO'], $result['child_np_communication_ids']);
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

        $this->assertSame([], $this->mergeService->clonedGames);
        $this->assertSame([], $this->mergeService->mergedGames);
        $this->assertSame([], $this->mergeService->copiedGames);
    }

    public function testIgnoresAlreadyMergedMatches(): void
    {
        $this->insertTitle(1, 'NP_NEW', 'Example Game', 'PS5');
        $this->insertTitle(2, 'NP_OLD', 'Example Game', 'PS4', 2);

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

        $this->assertSame([], $this->mergeService->clonedGames);
        $this->assertSame([], $this->mergeService->mergedGames);
        $this->assertSame([], $this->mergeService->copiedGames);
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
                status INTEGER NOT NULL DEFAULT 0,
                psnprofiles_id TEXT NULL,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0
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

        $this->database->exec(
            'CREATE TABLE trophy_merge (
                child_np_communication_id TEXT NOT NULL,
                child_group_id TEXT NULL,
                child_order_id INTEGER NULL,
                parent_np_communication_id TEXT NOT NULL,
                parent_group_id TEXT NULL,
                parent_order_id INTEGER NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_player (
                np_communication_id TEXT NOT NULL,
                account_id INTEGER NOT NULL,
                bronze INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0,
                platinum INTEGER NOT NULL DEFAULT 0,
                progress INTEGER NOT NULL DEFAULT 0,
                last_updated_date INTEGER NOT NULL DEFAULT 0
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

    private function insertTrophyMerge(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_merge (child_np_communication_id, parent_np_communication_id) VALUES (:child, :parent)'
        );
        $query->bindValue(':child', $childNpCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':parent', $parentNpCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function insertTrophyTitlePlayer(string $npCommunicationId, int $accountId, int $bronze, int $silver, int $gold, int $platinum, int $progress, int $lastUpdatedDate): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_title_player (np_communication_id, account_id, bronze, silver, gold, platinum, progress, last_updated_date) VALUES (:np, :account, :bronze, :silver, :gold, :platinum, :progress, :last_updated_date)'
        );
        $query->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':account', $accountId, PDO::PARAM_INT);
        $query->bindValue(':bronze', $bronze, PDO::PARAM_INT);
        $query->bindValue(':silver', $silver, PDO::PARAM_INT);
        $query->bindValue(':gold', $gold, PDO::PARAM_INT);
        $query->bindValue(':platinum', $platinum, PDO::PARAM_INT);
        $query->bindValue(':progress', $progress, PDO::PARAM_INT);
        $query->bindValue(':last_updated_date', $lastUpdatedDate, PDO::PARAM_INT);
        $query->execute();
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

    /** @var list<string> */
    public array $recomputedParents = [];

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

    public function recomputeMergeProgressByParent(string $parentNpCommunicationId): void
    {
        $this->recomputedParents[] = $parentNpCommunicationId;
    }
}
