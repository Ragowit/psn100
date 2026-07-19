<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportHandler.php';
require_once __DIR__ . '/PlayerReportResult.php';
require_once __DIR__ . '/PlayerReportRequest.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';

final readonly class PlayerReportPage
{
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
        array $serverParameters,
    ) {
        $this->playerSummary = $playerSummaryService->getSummary($accountId);
        $request = PlayerReportRequest::fromArrays($postParameters, $serverParameters);
        $this->explanation = $request->getExplanation();
        $this->explanationSubmitted = $request->wasExplanationSubmitted();
        $this->reportResult = $playerReportHandler->handleReportRequest($accountId, $request);
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

