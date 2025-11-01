<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMetaRepository.php';

final class TrophyMetaRepositoryTest extends TestCase
{
    private PDO $database;
    private TrophyMetaRepository $repository;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            'CREATE TABLE trophy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_meta (
                trophy_id INTEGER PRIMARY KEY,
                rarity_percent NUMERIC NOT NULL DEFAULT 0,
                rarity_point INTEGER NOT NULL DEFAULT 0,
                status INTEGER NOT NULL DEFAULT 0,
                owners INTEGER NOT NULL DEFAULT 0,
                rarity_name TEXT NOT NULL
            )'
        );

        $this->repository = new TrophyMetaRepository($this->database);
    }

    public function testEnsureExistsInsertsMetaWhenMissing(): void
    {
        $trophyId = $this->insertTrophy('NPWR-TEST', '01', 1);

        $this->repository->ensureExists('NPWR-TEST', '01', 1);

        $statement = $this->database->prepare('SELECT trophy_id, rarity_percent, rarity_point, status, owners, rarity_name FROM trophy_meta WHERE trophy_id = :trophy_id');
        $statement->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
        $statement->execute();

        $meta = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertTrue($meta !== false, 'Expected trophy_meta row to be created.');

        /** @var array<string, string> $normalized */
        $normalized = array_map(static fn ($value): string => (string) $value, $meta);

        $this->assertSame(
            [
                'trophy_id' => (string) $trophyId,
                'rarity_percent' => '0',
                'rarity_point' => '0',
                'status' => '0',
                'owners' => '0',
                'rarity_name' => 'NONE',
            ],
            $normalized
        );
    }

    public function testEnsureExistsDoesNotDuplicateExistingMeta(): void
    {
        $trophyId = $this->insertTrophy('NPWR-TEST', '02', 2);

        $this->database->prepare('INSERT INTO trophy_meta (trophy_id, rarity_name) VALUES (:trophy_id, :rarity_name)')
            ->execute([
                ':trophy_id' => $trophyId,
                ':rarity_name' => 'COMMON',
            ]);

        $this->repository->ensureExists('NPWR-TEST', '02', 2);

        $statement = $this->database->prepare('SELECT COUNT(*) FROM trophy_meta WHERE trophy_id = :trophy_id AND rarity_name = :rarity_name');
        $statement->bindValue(':trophy_id', $trophyId, PDO::PARAM_INT);
        $statement->bindValue(':rarity_name', 'COMMON', PDO::PARAM_STR);
        $statement->execute();

        $count = $statement->fetchColumn();

        $this->assertSame(1, (int) $count);
    }

    private function insertTrophy(string $npCommunicationId, string $groupId, int $orderId): int
    {
        $statement = $this->database->prepare('INSERT INTO trophy (np_communication_id, group_id, order_id) VALUES (:npid, :group_id, :order_id)');
        $statement->bindValue(':npid', $npCommunicationId, PDO::PARAM_STR);
        $statement->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $statement->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $statement->execute();

        return (int) $this->database->lastInsertId();
    }
}
