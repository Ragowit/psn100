<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportAdminService.php';
require_once __DIR__ . '/PlayerReportAdminPageResult.php';

class PlayerReportAdminPage
{
    private PlayerReportAdminService $playerReportAdminService;

    public function __construct(PlayerReportAdminService $playerReportAdminService)
    {
        $this->playerReportAdminService = $playerReportAdminService;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public function handle(array $queryParameters): PlayerReportAdminPageResult
    {
        $successMessage = null;
        $errorMessage = null;

        if (array_key_exists('delete', $queryParameters)) {
            $deleteId = $this->parseDeleteId($queryParameters['delete']);

            if ($deleteId === null) {
                $errorMessage = 'Please provide a valid report ID to delete.';
            } else {
                $this->playerReportAdminService->deleteReportById($deleteId);
                $successMessage = sprintf('Report %d deleted successfully.', $deleteId);
            }
        }

        $reportedPlayers = $this->playerReportAdminService->getReportedPlayers();

        return new PlayerReportAdminPageResult($reportedPlayers, $successMessage, $errorMessage);
    }

    private function parseDeleteId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);
        if ($trimmedValue === '') {
            return null;
        }

        if (!ctype_digit($trimmedValue)) {
            return null;
        }

        $deleteId = (int) $trimmedValue;

        return $deleteId > 0 ? $deleteId : null;
    }
}
