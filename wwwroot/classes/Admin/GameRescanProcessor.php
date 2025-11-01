<?php

declare(strict_types=1);

require_once __DIR__ . '/../TrophyCalculator.php';
require_once __DIR__ . '/GameRescanService.php';
require_once __DIR__ . '/GameRescanRequestHandler.php';

final class GameRescanProcessor
{
    private GameRescanRequestHandler $requestHandler;

    public function __construct(GameRescanRequestHandler $requestHandler)
    {
        $this->requestHandler = $requestHandler;
    }

    public static function fromDatabase(PDO $database): self
    {
        $trophyCalculator = new TrophyCalculator($database);
        $historyRecorder = new TrophyHistoryRecorder($database, new Psn100Logger($database));
        $gameRescanService = new GameRescanService($database, $trophyCalculator, $historyRecorder);
        $requestHandler = new GameRescanRequestHandler($gameRescanService);

        return new self($requestHandler);
    }

    /**
     * @param array<string, mixed> $postData
     * @param array<string, mixed> $serverData
     */
    public function processRequest(array $postData, array $serverData): void
    {
        $this->requestHandler->handleRequest($postData, $serverData);
    }
}

