<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportService.php';
require_once __DIR__ . '/PlayerReportResult.php';
require_once __DIR__ . '/PlayerReportRequest.php';
require_once __DIR__ . '/IpRateLimitService.php';

class PlayerReportHandler
{
    public function __construct(
        private readonly PlayerReportService $playerReportService,
        private readonly ?IpRateLimitService $rateLimitService = null,
    ) {
    }

    public function handleReportRequest(
        int $accountId,
        PlayerReportRequest $request
    ): PlayerReportResult {
        if (!$request->wasExplanationSubmitted()) {
            return PlayerReportResult::empty();
        }

        if (!$request->hasValidCsrfToken()) {
            return PlayerReportResult::error('Your session has expired. Please reload the page and try again.');
        }

        $explanation = $request->getExplanation();
        if ($explanation === '') {
            return PlayerReportResult::error('Please provide an explanation for your report.');
        }

        if (mb_strlen($explanation) > PlayerReportService::MAX_EXPLANATION_LENGTH) {
            return PlayerReportResult::error(
                'Explanation must be ' . PlayerReportService::MAX_EXPLANATION_LENGTH . ' characters or fewer.'
            );
        }

        if (
            $this->rateLimitService !== null
            && !$this->rateLimitService->checkAndRecord(
                $request->getIpAddress(),
                IpRateLimitService::BUCKET_PLAYER_REPORT
            )
        ) {
            return PlayerReportResult::error(
                'Too many report submissions. Please wait a moment and try again.'
            );
        }

        return $this->playerReportService->submitReport(
            $accountId,
            $request->getIpAddress(),
            $explanation
        );
    }
}
