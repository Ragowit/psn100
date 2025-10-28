<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyMergeProgressListener.php';

final class TrophyMergeServiceCopyMergedTrophiesTest extends TestCase
{
    public function testCopyMergedTrophiesIterativelyCopiesEarnedProgress(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pdo->exec(
            <<<'SQL'
            CREATE TABLE trophy_merge (
                child_np_communication_id TEXT NOT NULL,
                parent_np_communication_id TEXT NOT NULL,
                child_group_id TEXT NOT NULL,
                child_order_id INTEGER NOT NULL,
                parent_group_id TEXT NOT NULL,
                parent_order_id INTEGER NOT NULL
            )
        SQL
        );

        $pdo->exec(
            <<<'SQL'
            CREATE TABLE trophy_earned (
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                earned_date TEXT NULL,
                progress INTEGER NULL,
                earned INTEGER NOT NULL,
                PRIMARY KEY (np_communication_id, group_id, order_id, account_id)
            )
        SQL
        );

        $mergeInsert = $pdo->prepare(
            'INSERT INTO trophy_merge VALUES (:child_np, :parent_np, :child_group, :child_order, :parent_group, :parent_order)'
        );
        $mergeInsert->execute([
            ':child_np' => 'NP_CHILD',
            ':parent_np' => 'NP_PARENT',
            ':child_group' => '01',
            ':child_order' => 1,
            ':parent_group' => '00',
            ':parent_order' => 1,
        ]);
        $mergeInsert->execute([
            ':child_np' => 'NP_CHILD',
            ':parent_np' => 'NP_PARENT',
            ':child_group' => '02',
            ':child_order' => 2,
            ':parent_group' => '00',
            ':parent_order' => 2,
        ]);

        $earnedInsert = $pdo->prepare(
            <<<'SQL'
            INSERT INTO trophy_earned (
                np_communication_id,
                group_id,
                order_id,
                account_id,
                earned_date,
                progress,
                earned
            ) VALUES (:np, :group_id, :order_id, :account_id, :earned_date, :progress, :earned)
        SQL
        );

        // Child trophies that should be copied to the parent.
        $earnedInsert->execute([
            ':np' => 'NP_CHILD',
            ':group_id' => '01',
            ':order_id' => 1,
            ':account_id' => 1,
            ':earned_date' => '2024-01-03 10:00:00',
            ':progress' => 100,
            ':earned' => 1,
        ]);
        $earnedInsert->execute([
            ':np' => 'NP_CHILD',
            ':group_id' => '01',
            ':order_id' => 1,
            ':account_id' => 2,
            ':earned_date' => '2024-01-05 10:00:00',
            ':progress' => 80,
            ':earned' => 1,
        ]);
        $earnedInsert->execute([
            ':np' => 'NP_CHILD',
            ':group_id' => '02',
            ':order_id' => 2,
            ':account_id' => 1,
            ':earned_date' => '2024-02-01 09:00:00',
            ':progress' => 75,
            ':earned' => 0,
        ]);
        $earnedInsert->execute([
            ':np' => 'NP_CHILD',
            ':group_id' => '02',
            ':order_id' => 2,
            ':account_id' => 3,
            ':earned_date' => null,
            ':progress' => null,
            ':earned' => 0,
        ]);

        // Existing parent record that should be merged with child progress.
        $earnedInsert->execute([
            ':np' => 'NP_PARENT',
            ':group_id' => '00',
            ':order_id' => 1,
            ':account_id' => 2,
            ':earned_date' => '2024-01-10 12:00:00',
            ':progress' => 20,
            ':earned' => 0,
        ]);
        $earnedInsert->execute([
            ':np' => 'NP_PARENT',
            ':group_id' => '00',
            ':order_id' => 2,
            ':account_id' => 1,
            ':earned_date' => '2024-01-01 08:00:00',
            ':progress' => 20,
            ':earned' => 0,
        ]);

        $service = new TrophyMergeService($pdo);
        $progress = new RecordingProgressListener();

        $reflection = new ReflectionMethod(TrophyMergeService::class, 'copyMergedTrophies');
        $reflection->setAccessible(true);
        $reflection->invoke($service, 'NP_CHILD', $progress);

        $parentEarnedRows = $pdo->query(
            <<<'SQL'
            SELECT account_id, group_id, order_id, earned_date, progress, earned
            FROM trophy_earned
            WHERE np_communication_id = 'NP_PARENT'
            ORDER BY account_id, order_id
        SQL
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame(
            [
                [
                    'account_id' => 1,
                    'group_id' => '00',
                    'order_id' => 1,
                    'earned_date' => '2024-01-03 10:00:00',
                    'progress' => 100,
                    'earned' => 1,
                ],
                [
                    'account_id' => 1,
                    'group_id' => '00',
                    'order_id' => 2,
                    'earned_date' => '2024-01-01 08:00:00',
                    'progress' => 75,
                    'earned' => 0,
                ],
                [
                    'account_id' => 2,
                    'group_id' => '00',
                    'order_id' => 1,
                    'earned_date' => '2024-01-05 10:00:00',
                    'progress' => 80,
                    'earned' => 1,
                ],
                [
                    'account_id' => 3,
                    'group_id' => '00',
                    'order_id' => 2,
                    'earned_date' => null,
                    'progress' => null,
                    'earned' => 0,
                ],
            ],
            $parentEarnedRows,
            'Parent trophy progress was not synchronized as expected.'
        );

        $expectedEvents = [
            [73, 'Found 2 merged trophies to copy…'],
            [74, 'Copying merged trophies… (1/2)'],
            [75, 'Copying merged trophies… (2/2)'],
        ];
        $this->assertSame($expectedEvents, $progress->events, 'Unexpected progress events recorded.');
    }
}

final class RecordingProgressListener implements TrophyMergeProgressListener
{
    /** @var list<array{int, string}> */
    public array $events = [];

    public function onProgress(int $percent, string $message): void
    {
        $this->events[] = [$percent, $message];
    }
}
