<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisorPage.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisorService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisorFilter.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerSummaryService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerStatus.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class PlayerAdvisorPageTest extends TestCase
{
    private PDO $database;

    private PlayerAdvisorService $advisorService;

    private PlayerSummaryService $summaryService;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            <<<'SQL'
            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                name TEXT NOT NULL,
                icon_url TEXT NOT NULL,
                platform TEXT NOT NULL
            );

            CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL
            );

            CREATE TABLE trophy (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                np_communication_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                name TEXT NOT NULL,
                detail TEXT NOT NULL,
                icon_url TEXT NOT NULL,
                progress_target_value INTEGER,
                reward_name TEXT,
                reward_image_url TEXT
            );

            CREATE TABLE trophy_meta (
                trophy_id INTEGER PRIMARY KEY,
                rarity_percent REAL NOT NULL,
                in_game_rarity_percent REAL NOT NULL DEFAULT 0,
                status INTEGER NOT NULL
            );

            CREATE TABLE trophy_title_player (
                np_communication_id TEXT NOT NULL,
                account_id INTEGER NOT NULL,
                last_updated_date TEXT NOT NULL
            );

            CREATE TABLE trophy_earned (
                np_communication_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                earned INTEGER NOT NULL,
                progress REAL
            );
            SQL
        );

        $this->advisorService = new PlayerAdvisorService($this->database, new Utility());
        $this->summaryService = new class($this->database) extends PlayerSummaryService {
            public function getSummary(int $accountId): PlayerSummary
            {
                return new PlayerSummary(0, 0, null, 0);
            }
        };
    }

    public function testCurrentPageIsClampedWhenRequestedPageExceedsTotalPages(): void
    {
        $this->insertAdvisableTrophy(42, 1, 'Trophy One', 30.0);
        $this->insertAdvisableTrophy(42, 2, 'Trophy Two', 20.0);
        $this->insertAdvisableTrophy(42, 3, 'Trophy Three', 10.0);

        $page = new PlayerAdvisorPage(
            $this->advisorService,
            $this->summaryService,
            PlayerAdvisorFilter::fromArray(['page' => '999']),
            42,
            PlayerStatus::NORMAL
        );

        $this->assertSame(1, $page->getCurrentPage());
        $this->assertSame(1, $page->getTotalPages());
        $this->assertSame(0, $page->getOffset());
        $this->assertSame(3, $page->getTotalTrophies());
        $this->assertCount(3, $page->getAdvisableTrophies());
        $this->assertSame('Trophy One', $page->getAdvisableTrophies()[0]->getTrophyName());
    }

    public function testOutOfRangePageReturnsLastPageResults(): void
    {
        for ($index = 1; $index <= 55; $index++) {
            $this->insertAdvisableTrophy(42, $index, sprintf('Trophy %02d', $index), (float) $index);
        }

        $page = new PlayerAdvisorPage(
            $this->advisorService,
            $this->summaryService,
            PlayerAdvisorFilter::fromArray(['page' => '5']),
            42,
            PlayerStatus::NORMAL
        );

        $this->assertSame(2, $page->getCurrentPage());
        $this->assertSame(2, $page->getTotalPages());
        $this->assertSame(50, $page->getOffset());
        $this->assertSame(55, $page->getTotalTrophies());
        $this->assertCount(5, $page->getAdvisableTrophies());
        $this->assertSame('Trophy 05', $page->getAdvisableTrophies()[0]->getTrophyName());
        $this->assertSame('Trophy 01', $page->getAdvisableTrophies()[4]->getTrophyName());
    }

    public function testCurrentPageDefaultsToOneWhenNoAdvisableTrophiesExist(): void
    {
        $page = new PlayerAdvisorPage(
            $this->advisorService,
            $this->summaryService,
            PlayerAdvisorFilter::fromArray(['page' => '10']),
            42,
            PlayerStatus::NORMAL
        );

        $this->assertSame(1, $page->getCurrentPage());
        $this->assertSame(0, $page->getTotalPages());
        $this->assertSame(0, $page->getOffset());
        $this->assertSame(0, $page->getTotalTrophies());
        $this->assertCount(0, $page->getAdvisableTrophies());
    }

    private function insertAdvisableTrophy(int $accountId, int $trophyId, string $name, float $rarityPercent): void
    {
        $npCommunicationId = 'NPWR-ADVISOR-1';

        if ($trophyId === 1) {
            $this->database->exec(
                "INSERT INTO trophy_title (np_communication_id, name, icon_url, platform)
                VALUES ('{$npCommunicationId}', 'Advisor Game', 'game.png', 'PS5')"
            );
            $this->database->exec(
                "INSERT INTO trophy_title_meta (np_communication_id, status)
                VALUES ('{$npCommunicationId}', 0)"
            );
            $this->database->exec(
                "INSERT INTO trophy_title_player (np_communication_id, account_id, last_updated_date)
                VALUES ('{$npCommunicationId}', {$accountId}, '2024-01-01 00:00:00')"
            );
        }

        $statement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy (
                id,
                np_communication_id,
                order_id,
                type,
                name,
                detail,
                icon_url
            ) VALUES (
                :id,
                :np_communication_id,
                :order_id,
                'bronze',
                :name,
                'Detail',
                'trophy.png'
            )
            SQL
        );
        $statement->execute([
            ':id' => $trophyId,
            ':np_communication_id' => $npCommunicationId,
            ':order_id' => $trophyId,
            ':name' => $name,
        ]);

        $metaStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_meta (
                trophy_id,
                rarity_percent,
                status
            ) VALUES (
                :trophy_id,
                :rarity_percent,
                0
            )
            SQL
        );
        $metaStatement->execute([
            ':trophy_id' => $trophyId,
            ':rarity_percent' => $rarityPercent,
        ]);
    }
}
