<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameListPage.php';
require_once __DIR__ . '/../wwwroot/classes/GameListService.php';
require_once __DIR__ . '/../wwwroot/classes/GameListFilter.php';
require_once __DIR__ . '/../wwwroot/classes/SearchQueryHelper.php';

final class GameListPageTest extends TestCase
{
    private PDO $database;

    private GameListService $gameListService;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            <<<'SQL'
            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL,
                name TEXT NOT NULL,
                icon_url TEXT NOT NULL DEFAULT '',
                platform TEXT NOT NULL DEFAULT 'PS4',
                platinum INTEGER NOT NULL DEFAULT 0,
                gold INTEGER NOT NULL DEFAULT 0,
                silver INTEGER NOT NULL DEFAULT 0,
                bronze INTEGER NOT NULL DEFAULT 1
            );

            CREATE TABLE trophy_title_meta (
                np_communication_id TEXT PRIMARY KEY,
                status INTEGER NOT NULL DEFAULT 0,
                owners INTEGER NOT NULL DEFAULT 0,
                difficulty INTEGER NOT NULL DEFAULT 0,
                rarity_points INTEGER NOT NULL DEFAULT 0,
                in_game_rarity_points INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE player (
                online_id TEXT PRIMARY KEY,
                account_id TEXT,
                status INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE trophy_title_player (
                np_communication_id TEXT NOT NULL,
                account_id TEXT NOT NULL,
                progress INTEGER NOT NULL DEFAULT 0
            );
            SQL
        );

        $this->gameListService = new GameListService($this->database, new SearchQueryHelper());
    }

    public function testCurrentPageIsClampedWhenRequestedPageExceedsTotalPages(): void
    {
        $this->insertGame(1, 'Alpha Game');
        $this->insertGame(2, 'Bravo Game');
        $this->insertGame(3, 'Charlie Game');

        $page = new GameListPage(
            $this->gameListService,
            GameListFilter::fromArray(['page' => '999'])
        );

        $this->assertSame(1, $page->getCurrentPage());
        $this->assertSame(1, $page->getLastPage());
        $this->assertSame(3, $page->getTotalGames());
        $this->assertSame(1, $page->getRangeStart());
        $this->assertSame(3, $page->getRangeEnd());
        $this->assertCount(3, $page->getGames());
        $this->assertSame('Charlie Game', $page->getGames()[0]->getName());
    }

    public function testOutOfRangePageReturnsLastPageResults(): void
    {
        for ($id = 1; $id <= 45; $id++) {
            $this->insertGame($id, sprintf('Game %02d', $id));
        }

        $page = new GameListPage(
            $this->gameListService,
            GameListFilter::fromArray(['page' => '5'])
        );

        $this->assertSame(2, $page->getCurrentPage());
        $this->assertSame(2, $page->getLastPage());
        $this->assertSame(45, $page->getTotalGames());
        $this->assertSame(41, $page->getRangeStart());
        $this->assertSame(45, $page->getRangeEnd());
        $this->assertCount(5, $page->getGames());
        $this->assertSame('Game 05', $page->getGames()[0]->getName());
        $this->assertSame('Game 01', $page->getGames()[4]->getName());
    }

    public function testCurrentPageDefaultsToOneWhenNoGamesExist(): void
    {
        $page = new GameListPage(
            $this->gameListService,
            GameListFilter::fromArray(['page' => '10'])
        );

        $this->assertSame(1, $page->getCurrentPage());
        $this->assertSame(1, $page->getLastPage());
        $this->assertSame(0, $page->getTotalGames());
        $this->assertSame(0, $page->getRangeStart());
        $this->assertSame(0, $page->getRangeEnd());
        $this->assertCount(0, $page->getGames());
    }

    private function insertGame(int $id, string $name): void
    {
        $npCommunicationId = sprintf('NPWR%05d_00', $id);

        $titleStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_title (
                id,
                np_communication_id,
                name
            ) VALUES (
                :id,
                :np_communication_id,
                :name
            )
            SQL
        );
        $titleStatement->execute([
            ':id' => $id,
            ':np_communication_id' => $npCommunicationId,
            ':name' => $name,
        ]);

        $metaStatement = $this->database->prepare(
            <<<'SQL'
            INSERT INTO trophy_title_meta (
                np_communication_id
            ) VALUES (
                :np_communication_id
            )
            SQL
        );
        $metaStatement->execute([
            ':np_communication_id' => $npCommunicationId,
        ]);
    }
}
