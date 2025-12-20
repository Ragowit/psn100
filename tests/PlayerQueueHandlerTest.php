<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueHandler.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueRequest.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponseFactory.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueResponse.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerScanProgress.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerScanStatus.php';

final class ConfigurablePlayerQueueServiceStub extends PlayerQueueService
{
    private ?string $cheaterAccountId = null;

    private bool $hasReachedIpLimit = false;

    private bool $isValidPlayerName = true;

    private ?int $queuePosition = null;

    /** @var array{account_id: string|null, status: int|null}|null */
    private ?array $playerStatusData = null;

    /** @var list<array{playerName: string, ipAddress: string}> */
    private array $queuedPlayers = [];

    private bool $playerBeingScanned = false;

    private ?PlayerScanProgress $scanProgress = null;

    public function __construct()
    {
        // Parent constructor requires a PDO instance which is not needed for tests.
    }

    public function setCheaterAccountId(?string $cheaterAccountId): void
    {
        $this->cheaterAccountId = $cheaterAccountId;
    }

    public function getCheaterAccountId(string $playerName): ?string
    {
        return $this->cheaterAccountId;
    }

    public function setHasReachedIpSubmissionLimit(bool $hasReachedLimit): void
    {
        $this->hasReachedIpLimit = $hasReachedLimit;
    }

    public function hasReachedIpSubmissionLimit(string $ipAddress): bool
    {
        return $this->hasReachedIpLimit;
    }

    public function setIsValidPlayerName(bool $isValid): void
    {
        $this->isValidPlayerName = $isValid;
    }

    public function isValidPlayerName(string $playerName): bool
    {
        return $this->isValidPlayerName;
    }

    public function addPlayerToQueue(string $playerName, string $ipAddress): void
    {
        $this->queuedPlayers[] = [
            'playerName' => $playerName,
            'ipAddress' => $ipAddress,
        ];
    }

    /**
     * @return list<array{playerName: string, ipAddress: string}>
     */
    public function getQueuedPlayers(): array
    {
        return $this->queuedPlayers;
    }

    public function setPlayerBeingScanned(bool $playerBeingScanned): void
    {
        $this->playerBeingScanned = $playerBeingScanned;

        if (!$playerBeingScanned) {
            $this->scanProgress = null;
        }
    }

    /**
     * @param array{current?: int, total?: int, title?: string, npCommunicationId?: string}|null $progress
     */
    public function setScanProgress(?array $progress): void
    {
        if ($progress === null) {
            $this->playerBeingScanned = false;
            $this->scanProgress = null;

            return;
        }

        $this->playerBeingScanned = true;
        $this->scanProgress = PlayerScanProgress::fromArray($progress);
    }

    public function isPlayerBeingScanned(string $playerName): bool
    {
        return $this->playerBeingScanned;
    }

    public function getActiveScanStatus(string $playerName): ?PlayerScanStatus
    {
        if (!$this->playerBeingScanned) {
            return null;
        }

        return PlayerScanStatus::withProgress($this->scanProgress);
    }

    public function getActiveScanProgress(string $playerName): ?PlayerScanProgress
    {
        return $this->scanProgress;
    }

    public function setQueuePosition(?int $queuePosition): void
    {
        $this->queuePosition = $queuePosition;
    }

    public function getQueuePosition(string $playerName): ?int
    {
        return $this->queuePosition;
    }

    /**
     * @param array{account_id: string|null, status: int|null}|null $playerStatusData
     */
    public function setPlayerStatusData(?array $playerStatusData): void
    {
        $this->playerStatusData = $playerStatusData;
    }

    /**
     * @return array{account_id: string|null, status: int|null}|null
     */
    public function getPlayerStatusData(string $playerName): ?array
    {
        return $this->playerStatusData;
    }

    public function escapeHtml(string $value): string
    {
        return htmlentities($value, ENT_QUOTES, 'UTF-8');
    }
}

final class PlayerQueueHandlerTest extends TestCase
{
    public function testHandleAddToQueueRequestReturnsErrorForEmptyName(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => '   '], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $handler->handleAddToQueueRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertSame("PSN name can't be empty.", $response->getMessage());
    }

    public function testHandleAddToQueueRequestUsesDefaultResponseFactory(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $handler = new PlayerQueueHandler($service);
        $request = PlayerQueueRequest::fromArrays(['q' => 'QueuedUser'], ['REMOTE_ADDR' => '203.0.113.5']);

        $response = $handler->handleAddToQueueRequest($request);

        $this->assertSame('queued', $response->getStatus());
        $this->assertStringContainsString('QueuedUser', $response->getMessage());
        $queuedPlayers = $service->getQueuedPlayers();
        $this->assertCount(1, $queuedPlayers);
        $this->assertSame('QueuedUser', $queuedPlayers[0]['playerName']);
        $this->assertSame('203.0.113.5', $queuedPlayers[0]['ipAddress']);
    }

    public function testHandleAddToQueueRequestReturnsCheaterResponseWhenCheaterAccountFound(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setCheaterAccountId('Cheater/123');
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'BadUser'], ['REMOTE_ADDR' => '10.0.0.1']);

        $response = $handler->handleAddToQueueRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertStringContainsString("tagged as a cheater", $response->getMessage());
    }

    public function testHandleAddToQueueRequestReturnsQueueLimitResponseWhenIpLimitReached(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setHasReachedIpSubmissionLimit(true);
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'ValidUser'], ['REMOTE_ADDR' => '10.0.0.2']);

        $response = $handler->handleAddToQueueRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertSame(
            'You have already entered ' . PlayerQueueService::MAX_QUEUE_SUBMISSIONS_PER_IP
            . ' players into the queue. Please wait a while.',
            $response->getMessage()
        );
    }

    public function testHandleAddToQueueRequestReturnsInvalidNameResponseWhenNameIsInvalid(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setIsValidPlayerName(false);
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'invalid name'], ['REMOTE_ADDR' => '10.0.0.3']);

        $response = $handler->handleAddToQueueRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertSame(
            'PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) '
            . 'and underscores (_).',
            $response->getMessage()
        );
    }

    public function testHandleAddToQueueRequestQueuesPlayerWhenValidationPasses(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'ValidUser'], ['REMOTE_ADDR' => '192.168.0.1']);

        $response = $handler->handleAddToQueueRequest($request);

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());
        $this->assertStringContainsString('is being added to the queue', $response->getMessage());
        $this->assertSame(
            [
                ['playerName' => 'ValidUser', 'ipAddress' => '192.168.0.1'],
            ],
            $service->getQueuedPlayers()
        );
    }

    public function testHandleQueuePositionRequestReturnsErrorForEmptyName(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => ''], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $handler->handleQueuePositionRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertSame("PSN name can't be empty.", $response->getMessage());
    }

    public function testHandleQueuePositionRequestReturnsInvalidNameResponseWhenNameIsInvalid(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setIsValidPlayerName(false);
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'invalid name'], ['REMOTE_ADDR' => '10.0.0.4']);

        $response = $handler->handleQueuePositionRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertSame(
            'PSN name must contain between three and 16 characters, and can consist of letters, numbers, hyphens (-) '
            . 'and underscores (_).',
            $response->getMessage()
        );
    }

    public function testHandleQueuePositionRequestReturnsCheaterResponseWhenStatusMatches(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setPlayerStatusData([
            'account_id' => 'Cheater/123',
            'status' => PlayerQueueService::CHEATER_STATUS,
        ]);
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'Cheater'], ['REMOTE_ADDR' => '10.0.0.5']);

        $response = $handler->handleQueuePositionRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertStringContainsString('tagged as a cheater', $response->getMessage());
    }

    public function testHandleQueuePositionRequestReturnsQueuedForScanWhenPlayerBeingScanned(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setPlayerStatusData([
            'account_id' => null,
            'status' => null,
        ]);
        $service->setScanProgress([
            'current' => 3,
            'total' => 10,
            'title' => 'Game <Title>',
        ]);
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'ScanningUser'], ['REMOTE_ADDR' => '10.0.0.6']);

        $response = $handler->handleQueuePositionRequest($request);

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());
        $this->assertStringContainsString('is currently being scanned.', $response->getMessage());
        $this->assertStringContainsString('Currently scanning <strong>Game &lt;Title&gt;</strong> (3/10).', $response->getMessage());
        $this->assertStringContainsString('class="progress mt-2"', $response->getMessage());
    }

    public function testHandleQueuePositionRequestReturnsQueuePositionWhenAvailable(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setPlayerStatusData([
            'account_id' => null,
            'status' => null,
        ]);
        $service->setQueuePosition(5);
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'QueueUser'], ['REMOTE_ADDR' => '10.0.0.7']);

        $response = $handler->handleQueuePositionRequest($request);

        $this->assertSame('queued', $response->getStatus());
        $this->assertTrue($response->shouldPoll());
        $this->assertStringContainsString('currently in position 5.', $response->getMessage());
    }

    public function testHandleQueuePositionRequestReturnsPlayerNotFoundWhenNoDataExists(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'UnknownUser'], ['REMOTE_ADDR' => '10.0.0.8']);

        $response = $handler->handleQueuePositionRequest($request);

        $this->assertSame('error', $response->getStatus());
        $this->assertStringContainsString('was not found', $response->getMessage());
    }

    public function testHandleQueuePositionRequestReturnsQueueCompleteWhenPlayerRecentlyUpdated(): void
    {
        $service = new ConfigurablePlayerQueueServiceStub();
        $service->setPlayerStatusData([
            'account_id' => '12345',
            'status' => null,
        ]);
        $handler = new PlayerQueueHandler($service, new PlayerQueueResponseFactory($service));
        $request = PlayerQueueRequest::fromArrays(['q' => 'FinishedUser'], ['REMOTE_ADDR' => '10.0.0.9']);

        $response = $handler->handleQueuePositionRequest($request);

        $this->assertSame('complete', $response->getStatus());
        $this->assertFalse($response->shouldPoll());
        $this->assertStringContainsString('has been updated!', $response->getMessage());
    }
}
