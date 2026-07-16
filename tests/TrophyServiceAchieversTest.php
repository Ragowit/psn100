<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyService.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyAchiever.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyDetails.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTrophyProgress.php';

final class TrophyServiceAchieversTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE player (
                account_id INTEGER PRIMARY KEY,
                online_id TEXT NOT NULL,
                avatar_url TEXT,
                trophy_count_npwr INTEGER NOT NULL DEFAULT 0,
                trophy_count_sony INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->database->exec(
            'CREATE TABLE player_ranking (
                account_id INTEGER PRIMARY KEY,
                ranking INTEGER NOT NULL
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_earned (
                np_communication_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                earned INTEGER NOT NULL DEFAULT 1,
                earned_date TEXT
            )'
        );
    }

    public function testGetFirstAchieversOrdersByEarnedDateAscending(): void
    {
        $this->seedAchiever(1, 'first', 10, '2024-01-01 10:00:00');
        $this->seedAchiever(2, 'second', 20, '2024-01-02 10:00:00');
        $this->seedAchiever(3, 'unranked', 20000, '2023-01-01 10:00:00');

        $service = new TrophyService($this->database);
        $achievers = $service->getFirstAchievers('NPWR00001_00', 1);

        $this->assertCount(2, $achievers);
        $this->assertSame('first', $achievers[0]->getOnlineId());
        $this->assertSame('second', $achievers[1]->getOnlineId());
    }

    public function testGetLatestAchieversOrdersByEarnedDateDescending(): void
    {
        $this->seedAchiever(1, 'first', 10, '2024-01-01 10:00:00');
        $this->seedAchiever(2, 'second', 20, '2024-01-02 10:00:00');

        $service = new TrophyService($this->database);
        $achievers = $service->getLatestAchievers('NPWR00001_00', 1);

        $this->assertCount(2, $achievers);
        $this->assertSame('second', $achievers[0]->getOnlineId());
        $this->assertSame('first', $achievers[1]->getOnlineId());
    }

    public function testAchieversQueryDrivesFromPlayerRankingForPartitionPruning(): void
    {
        $source = file_get_contents(__DIR__ . '/../wwwroot/classes/TrophyService.php');
        $this->assertTrue(is_string($source));
        $this->assertStringContainsString('FROM' . "\n" . '                player_ranking r', $source);
        $this->assertStringContainsString('te.account_id = r.account_id', $source);
        $this->assertStringContainsString('r.ranking <= 10000', $source);
    }

    private function seedAchiever(int $accountId, string $onlineId, int $ranking, string $earnedDate): void
    {
        $this->database->prepare(
            'INSERT INTO player (account_id, online_id, avatar_url, trophy_count_npwr, trophy_count_sony)
             VALUES (:account_id, :online_id, :avatar_url, 1, 1)'
        )->execute([
            ':account_id' => $accountId,
            ':online_id' => $onlineId,
            ':avatar_url' => $onlineId . '.png',
        ]);

        $this->database->prepare(
            'INSERT INTO player_ranking (account_id, ranking) VALUES (:account_id, :ranking)'
        )->execute([
            ':account_id' => $accountId,
            ':ranking' => $ranking,
        ]);

        $this->database->prepare(
            'INSERT INTO trophy_earned (np_communication_id, order_id, account_id, earned, earned_date)
             VALUES (:np, 1, :account_id, 1, :earned_date)'
        )->execute([
            ':np' => 'NPWR00001_00',
            ':account_id' => $accountId,
            ':earned_date' => $earnedDate,
        ]);
    }
}
