<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameRecentPlayersService.php';
require_once __DIR__ . '/../wwwroot/classes/GamePlayerFilter.php';

final class GameRecentPlayersServiceTest extends TestCase
{
    private const NP_COMMUNICATION_ID = 'NPWR00001';

    private PDO $pdo;

    private GameRecentPlayersService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->createSchema();

        $this->service = new GameRecentPlayersService($this->pdo);
    }

    public function testGetGameReturnsGameDetailsWhenRecordExists(): void
    {
        $this->insertTrophyTitle([
            'id' => 1,
            'name' => 'Example Game',
            'detail' => 'Example detail',
            'np_communication_id' => self::NP_COMMUNICATION_ID,
            'parent_np_communication_id' => null,
            'platform' => 'PS5',
            'icon_url' => 'https://example.com/icon.png',
            'set_version' => '01.00',
            'region' => 'US',
            'message' => 'Play responsibly',
            'recent_players' => 10,
            'platinum' => 1,
            'gold' => 2,
            'silver' => 3,
            'bronze' => 4,
            'owners_completed' => 50,
            'owners' => 200,
            'difficulty' => 'Hard',
            'status' => 1,
            'psnprofiles_id' => null,
            'rarity_points' => 123,
        ]);

        $game = $this->service->getGame(1);

        $this->assertSame(1, $game?->getId());
        $this->assertSame('Example Game', $game?->getName());
        $this->assertSame(self::NP_COMMUNICATION_ID, $game?->getNpCommunicationId());
        $this->assertSame('PS5', $game?->getPlatform());
        $this->assertSame('https://example.com/icon.png', $game?->getIconUrl());
        $this->assertSame('Hard', $game?->getDifficulty());
        $this->assertSame(123, $game?->getRarityPoints());
        $this->assertTrue($game?->hasMessage() ?? false);
    }

    public function testGetGameReturnsNullWhenRecordMissing(): void
    {
        $this->assertSame(null, $this->service->getGame(99));
    }

    public function testGetPlayerAccountIdTrimsInputAndReturnsValue(): void
    {
        $this->insertPlayerRow([
            'account_id' => '99999999999999999999',
            'online_id' => 'TestPlayer',
            'avatar_url' => 'avatar.png',
            'country' => 'US',
            'trophy_count_npwr' => 10,
            'trophy_count_sony' => 12,
            'status' => 0,
        ]);

        $this->assertSame(
            '99999999999999999999',
            $this->service->getPlayerAccountId('  TestPlayer  ')
        );
        $this->assertSame(null, $this->service->getPlayerAccountId('   '));
        $this->assertSame(null, $this->service->getPlayerAccountId('Unknown'));
    }

    public function testGetGamePlayerReturnsGamePlayerProgressForMatchingRow(): void
    {
        $this->insertTrophyTitlePlayer([
            'np_communication_id' => self::NP_COMMUNICATION_ID,
            'account_id' => '88888888888888888888',
            'bronze' => 5,
            'silver' => 4,
            'gold' => 3,
            'platinum' => 2,
            'progress' => 87,
            'last_updated_date' => '2024-01-01T00:00:00',
        ]);

        $player = $this->service->getGamePlayer(self::NP_COMMUNICATION_ID, '88888888888888888888');

        $this->assertSame(self::NP_COMMUNICATION_ID, $player?->getNpCommunicationId());
        $this->assertSame('88888888888888888888', $player?->getAccountId());
        $this->assertSame(5, $player?->getBronzeCount());
        $this->assertSame(2, $player?->getPlatinumCount());
        $this->assertSame(87, $player?->getProgress());
    }

    public function testGetRecentPlayersRespectsLimitAndOrdersByLastUpdatedDate(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $accountId = sprintf('A%02d', $i);
            $onlineId = sprintf('Player%02d', $i);
            $lastUpdated = sprintf('2024-01-%02dT12:00:00', $i);

            $this->insertPlayerRow([
                'account_id' => $accountId,
                'online_id' => $onlineId,
                'avatar_url' => 'avatar' . $i . '.png',
                'country' => 'US',
                'trophy_count_npwr' => 100 + $i,
                'trophy_count_sony' => 110 + $i,
                'status' => 0,
            ]);

            $this->insertPlayerRanking($accountId, $i);

            $this->insertTrophyTitlePlayer([
                'np_communication_id' => self::NP_COMMUNICATION_ID,
                'account_id' => $accountId,
                'bronze' => $i,
                'silver' => $i + 1,
                'gold' => $i + 2,
                'platinum' => $i + 3,
                'progress' => min(100, $i * 10),
                'last_updated_date' => $lastUpdated,
            ]);
        }

        $players = $this->service->getRecentPlayers(
            self::NP_COMMUNICATION_ID,
            new GamePlayerFilter(null, null)
        );

        $this->assertCount(10, $players);
        $this->assertSame('Player12', $players[0]->getOnlineId());
        $this->assertSame('Player03', $players[9]->getOnlineId());
        $this->assertSame(12 + 3, $players[0]->getPlatinumCount());
        $this->assertSame('2024-01-12T12:00:00', $players[0]->getLastKnownDate());
    }

    public function testGetRecentPlayersAppliesFilterConditions(): void
    {
        $this->insertPlayerRow([
            'account_id' => 'A100',
            'online_id' => 'FilteredPlayer',
            'avatar_url' => 'avatar.png',
            'country' => 'US',
            'trophy_count_npwr' => 10,
            'trophy_count_sony' => 15,
            'status' => 0,
        ]);
        $this->insertPlayerRanking('A100', 50);
        $this->insertTrophyTitlePlayer([
            'np_communication_id' => self::NP_COMMUNICATION_ID,
            'account_id' => 'A100',
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            'progress' => 25,
            'last_updated_date' => '2024-02-01T00:00:00',
        ]);

        $this->insertPlayerRow([
            'account_id' => 'A101',
            'online_id' => 'ExcludedByCountry',
            'avatar_url' => 'avatar2.png',
            'country' => 'CA',
            'trophy_count_npwr' => 10,
            'trophy_count_sony' => 15,
            'status' => 0,
        ]);
        $this->insertPlayerRanking('A101', 51);
        $this->insertTrophyTitlePlayer([
            'np_communication_id' => self::NP_COMMUNICATION_ID,
            'account_id' => 'A101',
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            'progress' => 25,
            'last_updated_date' => '2024-02-02T00:00:00',
        ]);

        $this->insertPlayerRow([
            'account_id' => 'A102',
            'online_id' => 'ExcludedByStatus',
            'avatar_url' => 'avatar3.png',
            'country' => 'US',
            'trophy_count_npwr' => 10,
            'trophy_count_sony' => 15,
            'status' => 1,
        ]);
        $this->insertPlayerRanking('A102', 52);
        $this->insertTrophyTitlePlayer([
            'np_communication_id' => self::NP_COMMUNICATION_ID,
            'account_id' => 'A102',
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            'progress' => 25,
            'last_updated_date' => '2024-02-03T00:00:00',
        ]);

        $this->insertPlayerRow([
            'account_id' => 'A103',
            'online_id' => 'ExcludedByRanking',
            'avatar_url' => 'avatar4.png',
            'country' => 'US',
            'trophy_count_npwr' => 10,
            'trophy_count_sony' => 15,
            'status' => 0,
        ]);
        $this->insertPlayerRanking('A103', 10001);
        $this->insertTrophyTitlePlayer([
            'np_communication_id' => self::NP_COMMUNICATION_ID,
            'account_id' => 'A103',
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
            'progress' => 25,
            'last_updated_date' => '2024-02-04T00:00:00',
        ]);

        $players = $this->service->getRecentPlayers(
            self::NP_COMMUNICATION_ID,
            new GamePlayerFilter('US', null)
        );

        $this->assertCount(1, $players);
        $this->assertSame('FilteredPlayer', $players[0]->getOnlineId());
        $this->assertSame('US', $players[0]->getCountryCode());
    }

    private function createSchema(): void
    {
        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                name TEXT,
                detail TEXT,
                np_communication_id TEXT,
                platform TEXT,
                icon_url TEXT,
                set_version TEXT,
                platinum INTEGER,
                gold INTEGER,
                silver INTEGER,
                bronze INTEGER
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                parent_np_communication_id TEXT,
                region TEXT,
                message TEXT,
                recent_players INTEGER,
                owners_completed INTEGER,
                owners INTEGER,
                difficulty TEXT,
                status INTEGER,
                psnprofiles_id INTEGER,
                rarity_points INTEGER,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
                obsolete_ids TEXT
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE player (
                account_id TEXT PRIMARY KEY,
                online_id TEXT NOT NULL,
                avatar_url TEXT,
                country TEXT,
                trophy_count_npwr INTEGER,
                trophy_count_sony INTEGER,
                status INTEGER
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE player_ranking (
                account_id TEXT PRIMARY KEY,
                ranking INTEGER NOT NULL
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE trophy_title_player (
                np_communication_id TEXT NOT NULL,
                account_id TEXT NOT NULL,
                bronze INTEGER,
                silver INTEGER,
                gold INTEGER,
                platinum INTEGER,
                progress INTEGER,
                last_updated_date TEXT,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                common INTEGER NOT NULL DEFAULT 0,
                uncommon INTEGER NOT NULL DEFAULT 0,
                rare INTEGER NOT NULL DEFAULT 0,
                epic INTEGER NOT NULL DEFAULT 0,
                legendary INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_common INTEGER NOT NULL DEFAULT 0,
                in_game_uncommon INTEGER NOT NULL DEFAULT 0,
                in_game_rare INTEGER NOT NULL DEFAULT 0,
                in_game_epic INTEGER NOT NULL DEFAULT 0,
                in_game_legendary INTEGER NOT NULL DEFAULT 0
            )
            SQL
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insertTrophyTitle(array $values): void
    {
        $titleStatement = $this->pdo->prepare(
            <<<SQL
            INSERT INTO trophy_title (
                id,
                name,
                detail,
                np_communication_id,
                platform,
                icon_url,
                set_version,
                platinum,
                gold,
                silver,
                bronze
            ) VALUES (
                :id,
                :name,
                :detail,
                :np_communication_id,
                :platform,
                :icon_url,
                :set_version,
                :platinum,
                :gold,
                :silver,
                :bronze
            )
            SQL
        );

        $titleStatement->execute([
            ':id' => $values['id'],
            ':name' => $values['name'],
            ':detail' => $values['detail'],
            ':np_communication_id' => $values['np_communication_id'],
            ':platform' => $values['platform'],
            ':icon_url' => $values['icon_url'],
            ':set_version' => $values['set_version'],
            ':platinum' => $values['platinum'],
            ':gold' => $values['gold'],
            ':silver' => $values['silver'],
            ':bronze' => $values['bronze'],
        ]);

        $metaStatement = $this->pdo->prepare(
            <<<SQL
            INSERT INTO trophy_title_meta (
                np_communication_id,
                parent_np_communication_id,
                region,
                message,
                recent_players,
                owners_completed,
                owners,
                difficulty,
                status,
                psnprofiles_id,
                rarity_points
            ) VALUES (
                :np_communication_id,
                :parent_np_communication_id,
                :region,
                :message,
                :recent_players,
                :owners_completed,
                :owners,
                :difficulty,
                :status,
                :psnprofiles_id,
                :rarity_points
            )
            SQL
        );

        $metaStatement->execute([
            ':np_communication_id' => $values['np_communication_id'],
            ':parent_np_communication_id' => $values['parent_np_communication_id'],
            ':region' => $values['region'],
            ':message' => $values['message'],
            ':recent_players' => $values['recent_players'],
            ':owners_completed' => $values['owners_completed'],
            ':owners' => $values['owners'],
            ':difficulty' => $values['difficulty'],
            ':status' => $values['status'],
            ':psnprofiles_id' => $values['psnprofiles_id'],
            ':rarity_points' => $values['rarity_points'],
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insertPlayerRow(array $values): void
    {
        $statement = $this->pdo->prepare(
            <<<SQL
            INSERT INTO player (
                account_id,
                online_id,
                avatar_url,
                country,
                trophy_count_npwr,
                trophy_count_sony,
                status
            ) VALUES (
                :account_id,
                :online_id,
                :avatar_url,
                :country,
                :trophy_count_npwr,
                :trophy_count_sony,
                :status
            )
            SQL
        );

        $statement->execute([
            ':account_id' => $values['account_id'],
            ':online_id' => $values['online_id'],
            ':avatar_url' => $values['avatar_url'],
            ':country' => $values['country'],
            ':trophy_count_npwr' => $values['trophy_count_npwr'],
            ':trophy_count_sony' => $values['trophy_count_sony'],
            ':status' => $values['status'],
        ]);
    }

    private function insertPlayerRanking(string $accountId, int $ranking): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO player_ranking (account_id, ranking) VALUES (:account_id, :ranking)'
        );

        $statement->execute([
            ':account_id' => $accountId,
            ':ranking' => $ranking,
        ]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function insertTrophyTitlePlayer(array $values): void
    {
        $statement = $this->pdo->prepare(
            <<<SQL
            INSERT INTO trophy_title_player (
                np_communication_id,
                account_id,
                bronze,
                silver,
                gold,
                platinum,
                progress,
                last_updated_date
            ) VALUES (
                :np_communication_id,
                :account_id,
                :bronze,
                :silver,
                :gold,
                :platinum,
                :progress,
                :last_updated_date
            )
            SQL
        );

        $statement->execute([
            ':np_communication_id' => $values['np_communication_id'],
            ':account_id' => $values['account_id'],
            ':bronze' => $values['bronze'],
            ':silver' => $values['silver'],
            ':gold' => $values['gold'],
            ':platinum' => $values['platinum'],
            ':progress' => $values['progress'],
            ':last_updated_date' => $values['last_updated_date'],
        ]);
    }
}
