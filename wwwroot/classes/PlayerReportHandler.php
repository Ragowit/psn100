<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportService.php';
require_once __DIR__ . '/PlayerReportResult.php';

class PlayerReportHandler
{
    private PlayerReportService $playerReportService;

    public function __construct(PlayerReportService $playerReportService)
    {
        $this->playerReportService = $playerReportService;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public function getExplanation(array $queryParameters): string
    {
        return $this->sanitizeExplanation($queryParameters['explanation'] ?? null);
    }

    /**
     * @param int $accountId
     * @param string $explanation
     * @param bool $explanationSubmitted
     * @param array<string, mixed> $serverParameters
     */
    public function handleReportRequest(
        int $accountId,
        string $explanation,
        bool $explanationSubmitted,
        array $serverParameters
    ): PlayerReportResult
    {
        if (!$explanationSubmitted) {
            return PlayerReportResult::empty();
        }

        if ($explanation === '') {
            return PlayerReportResult::error('Please provide an explanation for your report.');
        }

        $ipAddress = $this->resolveIpAddress($serverParameters);

        return $this->playerReportService->submitReport($accountId, $ipAddress, $explanation);
    }

    private function sanitizeExplanation(mixed $explanation): string
    {
        if (!is_scalar($explanation)) {
            return '';
        }

        return trim((string) $explanation);
    }

    /**
     * @param array<string, mixed> $serverParameters
     */
    private function resolveIpAddress(array $serverParameters): string
    {
        $ipAddress = (string) ($serverParameters['REMOTE_ADDR'] ?? '');
        if ($ipAddress === '') {
            return '';
        }

        $validatedAddress = filter_var($ipAddress, FILTER_VALIDATE_IP);

        return is_string($validatedAddress) ? $validatedAddress : '';
    }
}
