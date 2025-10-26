<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerQueueService.php';

final class PlayerQueueServiceTest extends TestCase
{
    private PDO $pdo;

    private PlayerQueueService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE player_queue (
                online_id TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                request_time TEXT NOT NULL
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE player (
                online_id TEXT PRIMARY KEY,
                account_id TEXT,
                status INTEGER
            )
            SQL
        );

        $this->pdo->exec(
            <<<SQL
            CREATE TABLE setting (
                scanning TEXT
            )
            SQL
        );

        $this->service = new PlayerQueueService($this->pdo);
    }

    public function testGetIpSubmissionCountReturnsZeroWhenIpEmpty(): void
    {
        $this->assertSame(0, $this->service->getIpSubmissionCount(''));
    }

    public function testGetIpSubmissionCountReturnsNumberOfMatches(): void
    {
        $this->pdo->exec(
            "INSERT INTO player_queue (online_id, ip_address, request_time) VALUES" .
            " ('PlayerA', '127.0.0.1', '2024-01-01T00:00:00')," .
            " ('PlayerB', '127.0.0.1', '2024-01-01T00:01:00')," .
            " ('PlayerC', '10.0.0.1', '2024-01-01T00:02:00')"
        );

        $this->assertSame(2, $this->service->getIpSubmissionCount('127.0.0.1'));
    }

    public function testHasReachedIpSubmissionLimitChecksConfiguredThreshold(): void
    {
        $values = [];

        for ($i = 0; $i < PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP; $i++) {
            $values[] = sprintf("('Player%d', '192.168.0.1', '2024-01-01T00:%02d:00')", $i, $i);
        }

        $this->pdo->exec(
            'INSERT INTO player_queue (online_id, ip_address, request_time) VALUES ' .
            implode(', ', $values)
        );

        $this->assertTrue($this->service->hasReachedIpSubmissionLimit('192.168.0.1'));
        $this->assertFalse($this->service->hasReachedIpSubmissionLimit('10.0.0.1'));
    }

    public function testGetCheaterAccountIdReturnsMatchingAccount(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO player (online_id, account_id, status) VALUES (:online_id, :account_id, :status)'
        );
        $statement->execute([
            ':online_id' => 'KnownCheater',
            ':account_id' => '12345',
            ':status' => PlayerQueueService::CHEATER_STATUS,
        ]);

        $this->assertSame('12345', $this->service->getCheaterAccountId('KnownCheater'));
        $this->assertSame(null, $this->service->getCheaterAccountId('UnknownPlayer'));
    }

    public function testGetCheaterAccountIdReturnsNullForNonCheaterStatus(): void
    {
        $this->pdo->exec(
            "INSERT INTO player (online_id, account_id, status) VALUES" .
            " ('LegitPlayer', '67890', 0)"
        );

        $this->assertSame(null, $this->service->getCheaterAccountId('LegitPlayer'));
    }

    public function testIsValidPlayerNameValidatesAllowedCharactersAndLength(): void
    {
        $this->assertTrue($this->service->isValidPlayerName('Alpha-123'));
        $this->assertTrue($this->service->isValidPlayerName('ABC'));
        $this->assertTrue($this->service->isValidPlayerName('SixteenCharsHere'));

        $this->assertFalse($this->service->isValidPlayerName('ab'));
        $this->assertFalse($this->service->isValidPlayerName('name with space'));
        $this->assertFalse($this->service->isValidPlayerName('invalid!'));
        $this->assertFalse($this->service->isValidPlayerName(str_repeat('a', 17)));
    }

    public function testEscapeHtmlEncodesSpecialCharacters(): void
    {
        $this->assertSame(
            'Player &amp; &quot;&lt;Name&gt;&quot;',
            $this->service->escapeHtml('Player & "<Name>"')
        );
    }

    public function testIsPlayerBeingScannedChecksSettingTable(): void
    {
        $this->pdo->exec("INSERT INTO setting (scanning) VALUES ('ScanningPlayer')");

        $this->assertTrue($this->service->isPlayerBeingScanned('ScanningPlayer'));
        $this->assertFalse($this->service->isPlayerBeingScanned('AnotherPlayer'));
    }

    public function testGetQueuePositionReturnsRowNumberForPlayer(): void
    {
        $this->pdo->exec(
            "INSERT INTO player_queue (online_id, ip_address, request_time) VALUES" .
            " ('FirstPlayer', '1.1.1.1', '2024-01-01T00:00:01')," .
            " ('SecondPlayer', '1.1.1.2', '2024-01-01T00:00:02')," .
            " ('ThirdPlayer', '1.1.1.3', '2024-01-01T00:00:03')"
        );

        $this->assertSame(2, $this->service->getQueuePosition('SecondPlayer'));
        $this->assertSame(null, $this->service->getQueuePosition('MissingPlayer'));
    }

    public function testGetPlayerStatusDataReturnsNullWhenPlayerMissing(): void
    {
        $this->assertSame(null, $this->service->getPlayerStatusData('Nobody'));
    }

    public function testGetPlayerStatusDataNormalizesValues(): void
    {
        $this->pdo->exec(
            "INSERT INTO player (online_id, account_id, status) VALUES" .
            " ('SamplePlayer', 54321, NULL)"
        );

        $result = $this->service->getPlayerStatusData('SamplePlayer');

        $this->assertSame(
            ['account_id' => '54321', 'status' => null],
            $result
        );
    }

    public function testIsCheaterStatusMatchesConstant(): void
    {
        $this->assertTrue($this->service->isCheaterStatus(PlayerQueueService::CHEATER_STATUS));
        $this->assertFalse($this->service->isCheaterStatus(null));
        $this->assertFalse($this->service->isCheaterStatus(2));
    }
}
