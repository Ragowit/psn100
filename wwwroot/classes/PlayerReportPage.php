<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportHandler.php';
require_once __DIR__ . '/PlayerReportResult.php';
require_once __DIR__ . '/PlayerReportRequest.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';

class PlayerReportPage
{
    private PlayerReportHandler $playerReportHandler;

    private PlayerSummaryService $playerSummaryService;

    private int $accountId;

    /**
     * @var array<string, mixed>
     */
    private array $postParameters;

    /**
     * @var array<string, mixed>
     */
    private array $serverParameters;

    private PlayerSummary $playerSummary;

    private string $explanation;

    private bool $explanationSubmitted;

    private PlayerReportResult $reportResult;

    /**
     * @param array<string, mixed> $postParameters
     * @param array<string, mixed> $serverParameters
     */
    public function __construct(
        PlayerReportHandler $playerReportHandler,
        PlayerSummaryService $playerSummaryService,
        int $accountId,
        array $postParameters,
        array $serverParameters
    ) {
        $this->playerReportHandler = $playerReportHandler;
        $this->playerSummaryService = $playerSummaryService;
        $this->accountId = $accountId;
        $this->postParameters = $postParameters;
        $this->serverParameters = $serverParameters;

        $this->initialize();
    }

    private function initialize(): void
    {
        $this->playerSummary = $this->playerSummaryService->getSummary($this->accountId);
        $request = PlayerReportRequest::fromArrays($this->postParameters, $this->serverParameters);
        $this->explanation = $request->getExplanation();
        $this->explanationSubmitted = $request->wasExplanationSubmitted();
        $this->reportResult = $this->playerReportHandler->handleReportRequest(
            $this->accountId,
            $request
        );
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }

    public function wasExplanationSubmitted(): bool
    {
        return $this->explanationSubmitted;
    }

    public function getReportResult(): PlayerReportResult
    {
        return $this->reportResult;
    }
}

