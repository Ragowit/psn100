<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/AvatarPage.php';
require_once __DIR__ . '/../wwwroot/classes/AvatarService.php';
require_once __DIR__ . '/../wwwroot/classes/Avatar.php';

final class AvatarPageTest extends TestCase
{
    private PDO $database;

    private AvatarService $avatarService;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE player (avatar_url TEXT, status INTEGER)');

        $this->avatarService = new AvatarService($this->database);
    }

    protected function tearDown(): void
    {
        unset($this->avatarService);
        unset($this->database);
    }

    public function testGetAvatarsReturnsCountsForCurrentPage(): void
    {
        $this->insertPlayer('alpha.png');
        $this->insertPlayer('alpha.png');
        $this->insertPlayer('bravo.png');
        $this->insertPlayer('charlie.png', 1); // Flagged player should be ignored.

        $page = AvatarPage::fromQueryParameters($this->avatarService, ['page' => '1'], 2);
        $avatars = $page->getAvatars();

        $this->assertCount(2, $avatars);
        $this->assertSame('alpha.png', $avatars[0]->getUrl());
        $this->assertSame(2, $avatars[0]->getCount());
        $this->assertSame('bravo.png', $avatars[1]->getUrl());
        $this->assertSame(1, $avatars[1]->getCount());
    }

    public function testCurrentPageIsClampedWhenRequestedPageExceedsTotalPages(): void
    {
        $this->insertPlayer('alpha.png');
        $this->insertPlayer('bravo.png');
        $this->insertPlayer('charlie.png');

        $page = AvatarPage::fromQueryParameters($this->avatarService, ['page' => '5'], 2);

        $this->assertSame(2, $page->getCurrentPage());
        $this->assertSame(2, $page->getLastPage());

        $avatars = $page->getAvatars();
        $this->assertCount(1, $avatars);
        $this->assertSame('charlie.png', $avatars[0]->getUrl());
    }

    public function testCurrentPageDefaultsToOneWhenNoAvatarsExist(): void
    {
        $page = AvatarPage::fromQueryParameters($this->avatarService, ['page' => '10'], 5);

        $this->assertSame(1, $page->getCurrentPage());
        $this->assertSame(0, $page->getTotalPages());
        $this->assertSame(1, $page->getLastPage());
        $this->assertCount(0, $page->getAvatars());
    }

    private function insertPlayer(string $avatarUrl, int $status = 0): void
    {
        $statement = $this->database->prepare('INSERT INTO player (avatar_url, status) VALUES (:avatar_url, :status)');
        $statement->bindValue(':avatar_url', $avatarUrl, PDO::PARAM_STR);
        $statement->bindValue(':status', $status, PDO::PARAM_INT);
        $statement->execute();
    }
}
