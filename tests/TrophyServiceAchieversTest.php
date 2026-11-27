<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyService.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyAchiever.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyDetails.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTrophyProgress.php';

final class TrophyServiceAchieversTest extends TestCase
{
    private TrophyServiceAchieversTestDatabase $database;

    protected function setUp(): void
    {
        $this->database = new TrophyServiceAchieversTestDatabase('sqlite::memory:');
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
                earned_date TEXT,
                PRIMARY KEY (np_communication_id, order_id, account_id)
            )'
        );
    }

    public function testGetFirstAchieversOrdersByEarnedDateAscending(): void
    {
        $this->seedAchiever(2, 'second', 20, '2024-01-02 10:00:00');
        $this->seedAchiever(1, 'first', 10000, '2024-01-01 10:00:00');
        $this->seedAchiever(3, 'no-time', 30, null);

        $achievers = $this->getFirstAchievers();

        $this->assertSame(['first', 'second', 'no-time'], $this->onlineIds($achievers));
        $this->assertSame([
            '2024-01-01 10:00:00',
            '2024-01-02 10:00:00',
            'No Timestamp',
        ], $this->earnedDates($achievers));
        $this->assertSame('first.png', $achievers[0]->getAvatarUrl());
        $this->assertTrue($achievers[0]->hasHiddenTrophies());
        $this->assertSame(2, $this->database->firstAchieverQueryCount);
    }

    public function testGetFirstAchieversCapsTimestampedResultsAndSkipsFallback(): void
    {
        for ($i = 55; $i >= 1; $i--) {
            $this->seedAchiever(56 - $i, 'player-' . $i, $i, sprintf('2024-01-01 10:%02d:00', $i));
        }
        $this->seedAchiever(56, 'no-time', 1, null);

        $achievers = $this->getFirstAchievers();

        $this->assertSame(
            array_map(static fn (int $i): string => 'player-' . $i, range(1, 50)),
            $this->onlineIds($achievers)
        );
        $this->assertSame(1, $this->database->firstAchieverQueryCount);
    }

    public function testGetFirstAchieversFillsOnlyRemainingSlotsWhenTimestampedResultsTie(): void
    {
        $this->seedAchiever(1, 'tied-a', 10000, '2024-01-02 10:00:00');
        $this->seedAchiever(2, 'tied-b', 10000, '2024-01-02 10:00:00');
        $this->seedAchiever(3, 'first', 10000, '2024-01-01 10:00:00');
        for ($i = 4; $i <= 55; $i++) {
            $this->seedAchiever($i, 'no-time-' . $i, 1, null);
        }

        $achievers = $this->getFirstAchievers();

        $this->assertCount(50, $achievers);
        $this->assertSame('first', $achievers[0]->getOnlineId());
        $tiedPlayers = [$achievers[1]->getOnlineId(), $achievers[2]->getOnlineId()];
        sort($tiedPlayers);
        $this->assertSame(['tied-a', 'tied-b'], $tiedPlayers);
        $this->assertSame(
            array_fill(0, 47, 'No Timestamp'),
            $this->earnedDates(array_slice($achievers, 3))
        );
        $this->assertCount(50, array_unique($this->onlineIds($achievers)));
    }

    public function testGetFirstAchieversCanFillTheEntireListWithNullTimestamps(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            $this->seedAchiever($i, 'no-time-' . $i, 1, null);
        }

        $achievers = $this->getFirstAchievers();

        $this->assertSame(array_fill(0, 50, 'No Timestamp'), $this->earnedDates($achievers));
        $this->assertCount(50, array_unique($this->onlineIds($achievers)));
    }

    public function testGetFirstAchieversReturnsNoPlayersWhenAllRowsAreIneligible(): void
    {
        $date = '2024-01-01 10:00:00';
        $this->seedAchiever(1, 'outside-ranking', 10001, $date);
        $this->seedAchiever(2, 'outside-ranking-no-time', 10001, null);
        $this->seedAchiever(3, 'unearned', 1, $date, earned: 0);
        $this->seedAchiever(4, 'unearned-no-time', 1, null, earned: 0);
        $this->seedAchiever(5, 'different-trophy', 1, $date, orderId: 2);
        $this->seedAchiever(6, 'different-title', 1, null, npCommunicationId: 'NPWR00002_00');

        $this->assertSame([], $this->getFirstAchievers());
    }

    public function testGetFirstAchieversExcludesPreviouslySelectedAccountsFromFallback(): void
    {
        $this->seedAchiever(1, 'changed-timestamp', 1, '2024-01-01 10:00:00');
        for ($i = 2; $i <= 50; $i++) {
            $this->seedAchiever($i, 'no-time-' . $i, 1, null);
        }
        $this->database->beforeFallback = function (): void {
            $this->database->exec('UPDATE trophy_earned SET earned_date = NULL WHERE account_id = 1');
        };

        $achievers = $this->getFirstAchievers();

        $this->assertCount(50, $achievers);
        $this->assertSame('changed-timestamp', $achievers[0]->getOnlineId());
        $this->assertSame('2024-01-01 10:00:00', $achievers[0]->getEarnedDate());
        $this->assertCount(50, array_unique($this->onlineIds($achievers)));
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

    /** @return list<TrophyAchiever> */
    private function getFirstAchievers(): array
    {
        return new TrophyService($this->database)->getFirstAchievers('NPWR00001_00', 1);
    }

    /**
     * @param list<TrophyAchiever> $achievers
     * @return list<string>
     */
    private function onlineIds(array $achievers): array
    {
        return array_map(static fn (TrophyAchiever $achiever): string => $achiever->getOnlineId(), $achievers);
    }

    /**
     * @param list<TrophyAchiever> $achievers
     * @return list<string>
     */
    private function earnedDates(array $achievers): array
    {
        return array_map(static fn (TrophyAchiever $achiever): string => $achiever->getEarnedDate(), $achievers);
    }

    private function seedAchiever(
        int $accountId,
        string $onlineId,
        int $ranking,
        ?string $earnedDate,
        int $earned = 1,
        string $npCommunicationId = 'NPWR00001_00',
        int $orderId = 1
    ): void {
        $this->database->prepare(
            'INSERT INTO player (account_id, online_id, avatar_url, trophy_count_npwr, trophy_count_sony)
             VALUES (:account_id, :online_id, :avatar_url, 1, 2)'
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
             VALUES (:np, :order_id, :account_id, :earned, :earned_date)'
        )->execute([
            ':np' => $npCommunicationId,
            ':order_id' => $orderId,
            ':account_id' => $accountId,
            ':earned' => $earned,
            ':earned_date' => $earnedDate,
        ]);
    }
}

final class TrophyServiceAchieversTestDatabase extends PDO
{
    public int $firstAchieverQueryCount = 0;

    public ?Closure $beforeFallback = null;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FROM trophy_earned te')) {
            $this->firstAchieverQueryCount++;
            if (str_contains($query, 'te.earned_date IS NULL') && $this->beforeFallback !== null) {
                ($this->beforeFallback)();
            }
        }

        return parent::prepare($query, $options);
    }
}
