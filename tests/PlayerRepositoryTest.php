<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerRepository.php';

final class PlayerRepositoryTest extends TestCase
{
    private PDO $database;
    private PlayerRepository $repository;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE player (
                account_id TEXT PRIMARY KEY,
                online_id TEXT NOT NULL,
                country TEXT NOT NULL,
                avatar_url TEXT,
                plus INTEGER NOT NULL DEFAULT 0,
                about_me TEXT NOT NULL DEFAULT ""
            )'
        );

        $this->repository = new PlayerRepository($this->database);
    }

    public function testUpsertFromPsnProfileInsertsNewPlayer(): void
    {
        $this->repository->upsertFromPsnProfile(
            '12345',
            'ExampleUser',
            'US',
            'avatar.png',
            true,
            'Hello world',
        );

        $statement = $this->database->query(
            'SELECT account_id, online_id, country, avatar_url, plus, about_me FROM player'
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertSame([
            'account_id' => '12345',
            'online_id' => 'ExampleUser',
            'country' => 'us',
            'avatar_url' => 'avatar.png',
            'plus' => 1,
            'about_me' => 'Hello world',
        ], $row);
    }

    public function testUpsertFromPsnProfileUpdatesExistingPlayerWithoutChangingCountry(): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO player (account_id, online_id, country, avatar_url, plus, about_me)
            VALUES (:account_id, :online_id, :country, :avatar_url, :plus, :about_me)'
        );
        $statement->execute([
            ':account_id' => '12345',
            ':online_id' => 'OldName',
            ':country' => 'se',
            ':avatar_url' => 'old.png',
            ':plus' => 0,
            ':about_me' => 'Old bio',
        ]);

        $this->repository->upsertFromPsnProfile(
            '12345',
            'NewName',
            'US',
            'new.png',
            true,
            'Updated bio',
        );

        $statement = $this->database->query(
            'SELECT account_id, online_id, country, avatar_url, plus, about_me FROM player'
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertSame([
            'account_id' => '12345',
            'online_id' => 'NewName',
            'country' => 'se',
            'avatar_url' => 'new.png',
            'plus' => 1,
            'about_me' => 'Updated bio',
        ], $row);
    }
}
