<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerCountryResolver.php';

if (!class_exists('Tustin\\PlayStation\\Client')) {
    eval('namespace Tustin\\PlayStation; final class Client {}');
}

final class PlayerCountryResolverTest extends TestCase
{
    private PlayerCountryResolver $resolver;
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE player (
                account_id TEXT PRIMARY KEY,
                online_id TEXT NOT NULL,
                country TEXT
            )'
        );

        $this->resolver = new PlayerCountryResolver($this->database);
    }

    public function testExtractCountryFromNpIdDecodesTrailingCountryCode(): void
    {
        $npId = base64_encode('ExamplePlayerUS');

        $result = $this->resolver->extractCountryFromNpId($npId);

        $this->assertSame('us', $result);
    }

    public function testExtractCountryFromNpIdReturnsNullForInvalidValues(): void
    {
        $this->assertSame(null, $this->resolver->extractCountryFromNpId(''));
        $this->assertSame(null, $this->resolver->extractCountryFromNpId(null));
        $this->assertSame(null, $this->resolver->extractCountryFromNpId('not-valid-base64!!!'));
    }

    public function testFetchStoredCountryByAccountIdReturnsStoredValue(): void
    {
        $largeAccountId = '9223372036854775808';
        $statement = $this->database->prepare(
            'INSERT INTO player (account_id, online_id, country) VALUES (:account_id, :online_id, :country)'
        );
        $statement->bindValue(':account_id', $largeAccountId, PDO::PARAM_STR);
        $statement->bindValue(':online_id', 'large-id-player', PDO::PARAM_STR);
        $statement->bindValue(':country', 'se', PDO::PARAM_STR);
        $statement->execute();

        $this->assertSame('se', $this->resolver->fetchStoredCountryByAccountId($largeAccountId));
    }

    public function testUpdatePlayerCountryPersistsLowercaseCountryCode(): void
    {
        $largeAccountId = '9223372036854775808';
        $statement = $this->database->prepare(
            'INSERT INTO player (account_id, online_id, country) VALUES (:account_id, :online_id, :country)'
        );
        $statement->bindValue(':account_id', $largeAccountId, PDO::PARAM_STR);
        $statement->bindValue(':online_id', 'large-id-player', PDO::PARAM_STR);
        $statement->bindValue(':country', 'se', PDO::PARAM_STR);
        $statement->execute();

        $this->resolver->updatePlayerCountry($largeAccountId, 'NO');

        $country = $this->database
            ->query("SELECT country FROM player WHERE account_id = '{$largeAccountId}'")
            ->fetchColumn();
        $this->assertSame('no', $country);
    }

    public function testResolveCountryUsesNpIdHintWithoutQueryingStoredCountry(): void
    {
        $accountId = '12345';
        $statement = $this->database->prepare(
            'INSERT INTO player (account_id, online_id, country) VALUES (:account_id, :online_id, :country)'
        );
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $statement->bindValue(':online_id', 'player-one', PDO::PARAM_STR);
        $statement->bindValue(':country', 'zz', PDO::PARAM_STR);
        $statement->execute();

        $client = new \Tustin\PlayStation\Client();

        $country = $this->resolver->resolveCountry($client, $accountId, 'player-one', 'us');

        $this->assertSame('us', $country);

        $storedCountry = $this->database
            ->query("SELECT country FROM player WHERE account_id = '{$accountId}'")
            ->fetchColumn();
        $this->assertSame('us', $storedCountry);
    }

    public function testResolveCountryFallsBackToStoredCountry(): void
    {
        $accountId = '12345';
        $statement = $this->database->prepare(
            'INSERT INTO player (account_id, online_id, country) VALUES (:account_id, :online_id, :country)'
        );
        $statement->bindValue(':account_id', $accountId, PDO::PARAM_STR);
        $statement->bindValue(':online_id', 'player-one', PDO::PARAM_STR);
        $statement->bindValue(':country', 'fi', PDO::PARAM_STR);
        $statement->execute();

        $client = new \Tustin\PlayStation\Client();

        $country = $this->resolver->resolveCountry($client, $accountId, 'player-one');

        $this->assertSame('fi', $country);
    }
}
