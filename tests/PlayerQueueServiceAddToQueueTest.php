<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerQueueService.php';
require_once __DIR__ . '/../wwwroot/classes/IpSubmissionLockExecutor.php';

final class PlayerQueueServiceAddToQueueTest extends TestCase
{
    private PDO $pdo;

    private PlayerQueueService $service;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE player_queue (
                online_id TEXT PRIMARY KEY,
                ip_address TEXT NOT NULL,
                request_time TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
            SQL
        );

        $this->service = new PlayerQueueService($this->pdo);
    }

    public function testAddPlayerToQueueReturnsFalseWhenIpLimitReached(): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO player_queue (online_id, ip_address) VALUES (:online_id, :ip_address)'
        );

        for ($index = 0; $index < PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP; $index++) {
            $insert->execute([
                ':online_id' => 'Player' . $index,
                ':ip_address' => '192.0.2.10',
            ]);
        }

        $result = $this->service->addPlayerToQueue('NewPlayer', '192.0.2.10');

        $this->assertFalse($result);
        $this->assertSame(10, (int) $this->pdo->query('SELECT COUNT(*) FROM player_queue')->fetchColumn());
    }

    public function testAddPlayerToQueueAllowsRequeueForExistingPlayerWhenIpLimitReached(): void
    {
        $insert = $this->pdo->prepare(
            'INSERT INTO player_queue (online_id, ip_address) VALUES (:online_id, :ip_address)'
        );

        for ($index = 0; $index < PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP; $index++) {
            $insert->execute([
                ':online_id' => 'Player' . $index,
                ':ip_address' => '192.0.2.11',
            ]);
        }

        $insert->execute([
            ':online_id' => 'ExistingPlayer',
            ':ip_address' => '192.0.2.11',
        ]);

        $result = $this->service->addPlayerToQueue('ExistingPlayer', '192.0.2.11');

        $this->assertTrue($result);
        $this->assertSame(11, (int) $this->pdo->query('SELECT COUNT(*) FROM player_queue')->fetchColumn());
    }

    public function testAddPlayerToQueueInsertsNewPlayerWhenUnderIpLimit(): void
    {
        $result = $this->service->addPlayerToQueue('FreshPlayer', '192.0.2.12');

        $this->assertTrue($result);
        $this->assertTrue($this->service->isPlayerInQueue('FreshPlayer'));
    }
}
