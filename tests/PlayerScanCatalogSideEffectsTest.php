<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyHistoryRecorder.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanNewTitleMergeHandler.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanCatalogSideEffects.php';

final class PlayerScanCatalogSideEffectsTest extends TestCase
{
    private PDO $database;

    /** @var RecordingPlayerScanCatalogHistoryRecorder */
    private RecordingPlayerScanCatalogHistoryRecorder $historyRecorder;

    /** @var RecordingPlayerScanCatalogMergeService */
    private RecordingPlayerScanCatalogMergeService $mergeService;

    private PlayerScanCatalogSideEffects $sideEffects;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec('CREATE TABLE trophy_title (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            np_communication_id TEXT NOT NULL
        )');
        $this->database->exec('CREATE TABLE psn100_change (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            change_type TEXT NOT NULL,
            param_1 INTEGER NOT NULL
        )');

        $this->historyRecorder = new RecordingPlayerScanCatalogHistoryRecorder();
        $this->mergeService = new RecordingPlayerScanCatalogMergeService(['NPWR_PARENT_00']);

        $this->sideEffects = new PlayerScanCatalogSideEffects(
            $this->database,
            $this->historyRecorder,
            $this->mergeService,
        );
    }

    public function testDoesNothingWhenNoCatalogChangesOrTriggers(): void
    {
        $result = $this->sideEffects->applyAfterCatalogSync(
            npCommunicationId: 'NPWR12345_00',
            titleDataChanged: false,
            groupDataChanged: false,
            trophyDataChanged: false,
            isNewTitle: false,
            newTrophies: false,
        );

        $this->assertSame(null, $result->titleId);
        $this->assertSame([], $result->mergeParentsToRecompute);
        $this->assertSame([], $this->historyRecorder->recordedTitleIds);
        $this->assertSame([], $this->mergeService->handledNpIds);
        $this->assertSame(0, $this->countChangelogRows());
    }

    public function testRecordsHistoryWhenCatalogDataChanged(): void
    {
        $this->insertTitle('NPWR12345_00', 42);

        $result = $this->sideEffects->applyAfterCatalogSync(
            npCommunicationId: 'NPWR12345_00',
            titleDataChanged: true,
            groupDataChanged: false,
            trophyDataChanged: false,
            isNewTitle: false,
            newTrophies: false,
        );

        $this->assertSame(42, $result->titleId);
        $this->assertSame([42], $this->historyRecorder->recordedTitleIds);
        $this->assertSame(0, $this->countChangelogRows());
    }

    public function testTriggersAutomaticMergeForNewTitles(): void
    {
        $result = $this->sideEffects->applyAfterCatalogSync(
            npCommunicationId: 'NPWR12345_00',
            titleDataChanged: false,
            groupDataChanged: false,
            trophyDataChanged: false,
            isNewTitle: true,
            newTrophies: false,
        );

        $this->assertSame(['NPWR12345_00'], $this->mergeService->handledNpIds);
        $this->assertSame(['NPWR_PARENT_00'], $result->mergeParentsToRecompute);
    }

    public function testInsertsGameVersionChangelogWhenNewTrophiesAppear(): void
    {
        $this->insertTitle('NPWR12345_00', 7);

        $result = $this->sideEffects->applyAfterCatalogSync(
            npCommunicationId: 'NPWR12345_00',
            titleDataChanged: false,
            groupDataChanged: false,
            trophyDataChanged: false,
            isNewTitle: false,
            newTrophies: true,
        );

        $this->assertSame(7, $result->titleId);
        $this->assertSame(1, $this->countChangelogRows());

        $query = $this->database->query('SELECT change_type, param_1 FROM psn100_change');
        $row = $query->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('GAME_VERSION', $row['change_type']);
        $this->assertSame(7, (int) $row['param_1']);
    }

    public function testReusesProvidedTitleIdWithoutLookup(): void
    {
        $result = $this->sideEffects->applyAfterCatalogSync(
            npCommunicationId: 'NPWR12345_00',
            titleDataChanged: true,
            groupDataChanged: false,
            trophyDataChanged: false,
            isNewTitle: false,
            newTrophies: true,
            titleId: 99,
        );

        $this->assertSame(99, $result->titleId);
        $this->assertSame([99], $this->historyRecorder->recordedTitleIds);
        $this->assertSame(1, $this->countChangelogRows());
    }

    private function insertTitle(string $npCommunicationId, int $id): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_title (id, np_communication_id) VALUES (:id, :np_communication_id)'
        );
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function countChangelogRows(): int
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM psn100_change')->fetchColumn();
    }
}

final class RecordingPlayerScanCatalogHistoryRecorder extends TrophyHistoryRecorder
{
    /** @var list<int> */
    public array $recordedTitleIds = [];

    public function __construct()
    {
    }

    public function recordByTitleId(int $titleId): void
    {
        $this->recordedTitleIds[] = $titleId;
    }
}

final class RecordingPlayerScanCatalogMergeService implements PlayerScanNewTitleMergeHandler
{
    /** @var list<string> */
    public array $handledNpIds = [];

    /**
     * @param list<string> $parentsToReturn
     */
    public function __construct(private readonly array $parentsToReturn)
    {
    }

    public function handleNewTitle(string $npCommunicationId): array
    {
        $this->handledNpIds[] = $npCommunicationId;

        return $this->parentsToReturn;
    }
}
