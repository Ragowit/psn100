<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportService.php';
require_once __DIR__ . '/PlayerReportResult.php';
require_once __DIR__ . '/PlayerReportRequest.php';

class PlayerReportHandler
{
    private PlayerReportService $playerReportService;

    public function __construct(PlayerReportService $playerReportService)
    {
        $this->playerReportService = $playerReportService;
    }

    public function handleReportRequest(
        int $accountId,
        PlayerReportRequest $request
    ): PlayerReportResult {
        if (!$request->wasExplanationSubmitted()) {
            return PlayerReportResult::empty();
        }

        $explanation = $request->getExplanation();
        if ($explanation === '') {
            return PlayerReportResult::error('Please provide an explanation for your report.');
        }

        return $this->playerReportService->submitReport(
            $accountId,
            $request->getIpAddress(),
            $explanation
        );
    }
}
