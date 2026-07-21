<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerReportAdminService.php';
require_once __DIR__ . '/PlayerReportAdminPageResult.php';
require_once __DIR__ . '/PlayerReportDeletionRequest.php';
require_once __DIR__ . '/../HttpMethod.php';

final readonly class PlayerReportAdminPage
{
    public function __construct(
        final private PlayerReportAdminService $playerReportAdminService,
    ) {
    }

    /**
     * @param array<string, mixed> $queryParameters
     * @param array<string, mixed> $postData
     */
    public function handle(array $queryParameters, array $postData, string $method): PlayerReportAdminPageResult
    {
        $successMessage = null;
        $errorMessage = null;

        if (HttpMethod::fromMixed($method)->isPost()) {
            $deletionRequest = PlayerReportDeletionRequest::fromPostData($postData);

            if ($deletionRequest->hasError()) {
                $errorMessage = $deletionRequest->getErrorMessage();
            } elseif ($deletionRequest->isValidDeletion()) {
                $deleteId = $deletionRequest->getDeleteId();

                if ($deleteId !== null) {
                    $this->playerReportAdminService->deleteReportById($deleteId);
                    $successMessage = sprintf('Report %d deleted successfully.', $deleteId);
                }
            }
        }

        $reportedPlayers = $this->playerReportAdminService->getReportedPlayers();

        return new PlayerReportAdminPageResult($reportedPlayers, $successMessage, $errorMessage);
    }
}
