<?php

declare(strict_types=1);

require_once __DIR__ . '/ReportedPlayer.php';

final readonly class PlayerReportAdminPageResult
{
    /**
     * @param ReportedPlayer[] $reportedPlayers
     */
    public function __construct(
        private array $reportedPlayers,
        private ?string $successMessage = null,
        private ?string $errorMessage = null,
    ) {
    }

    /**
     * @return ReportedPlayer[]
     */
    public function getReportedPlayers(): array
    {
        return $this->reportedPlayers;
    }

    public function getSuccessMessage(): ?string
    {
        return $this->successMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
