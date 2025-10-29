<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisorService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisorFilter.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisableTrophy.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class PlayerAdvisorServiceTest extends TestCase
{
    private PDO $database;

    private PlayerAdvisorService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            'CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                name TEXT NOT NULL,
                icon_url TEXT NOT NULL,
                platform TEXT NOT NULL,
                status INTEGER NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                name TEXT NOT NULL,
                detail TEXT NOT NULL,
                icon_url TEXT NOT NULL,
                rarity_percent REAL NOT NULL,
                progress_target_value INTEGER,
                reward_name TEXT,
                reward_image_url TEXT,
                status INTEGER NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_title_player (
                np_communication_id TEXT NOT NULL,
                account_id INTEGER NOT NULL,
                last_updated_date TEXT NOT NULL
            )'
        );

        $this->database->exec(
            'CREATE TABLE trophy_earned (
                np_communication_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                earned INTEGER NOT NULL,
                progress REAL
            )'
        );

        $this->service = new PlayerAdvisorService($this->database, new Utility());
    }

    public function testCountAdvisableTrophiesIgnoresEarnedAndUnselectedPlatforms(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, name, icon_url, platform, status) VALUES\n" .
            "('NPWR-PS5-1', 'Game PS5', 'game-ps5.png', 'PS5', 0),\n" .
            "('NPWR-PS5-2', 'Game PS5 Earned', 'game-ps5-earned.png', 'PS5', 0),\n" .
            "('NPWR-PS4-1', 'Game PS4', 'game-ps4.png', 'PS4', 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy (np_communication_id, order_id, type, name, detail, icon_url, rarity_percent, progress_target_value, reward_name, reward_image_url, status) VALUES\n" .
            "('NPWR-PS5-1', 1, 'bronze', 'Unearned Trophy', 'Complete a task', 'trophy-1.png', 12.5, NULL, NULL, NULL, 0),\n" .
            "('NPWR-PS5-2', 1, 'bronze', 'Earned Trophy', 'Already done', 'trophy-2.png', 20.0, NULL, NULL, NULL, 0),\n" .
            "('NPWR-PS4-1', 1, 'bronze', 'Different Platform', 'Wrong platform', 'trophy-3.png', 30.0, NULL, NULL, NULL, 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_player (np_communication_id, account_id, last_updated_date) VALUES\n" .
            "('NPWR-PS5-1', 42, '2024-01-01 10:00:00'),\n" .
            "('NPWR-PS5-2', 42, '2024-01-01 11:00:00'),\n" .
            "('NPWR-PS4-1', 42, '2024-01-01 12:00:00')"
        );

        $this->database->exec(
            "INSERT INTO trophy_earned (np_communication_id, order_id, account_id, earned, progress) VALUES\n" .
            "('NPWR-PS5-2', 1, 42, 1, NULL)"
        );

        $filter = PlayerAdvisorFilter::fromArray(['ps5' => '1']);

        $count = $this->service->countAdvisableTrophies(42, $filter);

        $this->assertSame(1, $count);
    }

    public function testGetAdvisableTrophiesReturnsOrderedTrophyCollection(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, name, icon_url, platform, status) VALUES\n" .
            "('NPWR-1', 'First Game', 'game-1.png', 'PS4', 0),\n" .
            "('NPWR-2', 'Second Game', 'game-2.png', 'PS4', 0),\n" .
            "('NPWR-3', 'Third Game', 'game-3.png', 'PS4', 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy (np_communication_id, order_id, type, name, detail, icon_url, rarity_percent, progress_target_value, reward_name, reward_image_url, status) VALUES\n" .
            "('NPWR-1', 1, 'bronze', 'First Trophy', 'Description 1', 'trophy-1.png', 15.0, NULL, NULL, NULL, 0),\n" .
            "('NPWR-2', 1, 'silver', 'Second Trophy', 'Description 2', 'trophy-2.png', 20.0, NULL, NULL, NULL, 0),\n" .
            "('NPWR-3', 1, 'gold', 'Third Trophy', 'Description 3', 'trophy-3.png', 15.0, 100, 'Reward', 'reward.png', 0)"
        );

        $this->database->exec(
            "INSERT INTO trophy_title_player (np_communication_id, account_id, last_updated_date) VALUES\n" .
            "('NPWR-1', 99, '2024-01-02 08:00:00'),\n" .
            "('NPWR-2', 99, '2024-01-01 09:00:00'),\n" .
            "('NPWR-3', 99, '2024-01-03 07:00:00')"
        );

        $this->database->exec(
            "INSERT INTO trophy_earned (np_communication_id, order_id, account_id, earned, progress) VALUES\n" .
            "('NPWR-3', 1, 99, 0, 25.0)"
        );

        $filter = PlayerAdvisorFilter::fromArray([]);

        $trophies = $this->service->getAdvisableTrophies(99, $filter, 0, 50);

        $this->assertCount(3, $trophies);
        $this->assertSame(PlayerAdvisableTrophy::class, get_class($trophies[0]));

        $this->assertSame(
            [2, 3, 1],
            [
                $trophies[0]->getTrophyId(),
                $trophies[1]->getTrophyId(),
                $trophies[2]->getTrophyId(),
            ]
        );

        $this->assertSame('25/100', $trophies[1]->getProgressTargetLabel());
    }
}
