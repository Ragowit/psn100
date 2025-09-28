<?php

declare(strict_types=1);

require_once __DIR__ . '/ReportedPlayer.php';

class PlayerReportAdminPageResult
{
    /**
     * @var ReportedPlayer[]
     */
    private array $reportedPlayers;

    private ?string $successMessage;

    private ?string $errorMessage;

    /**
     * @param ReportedPlayer[] $reportedPlayers
     */
    public function __construct(array $reportedPlayers, ?string $successMessage = null, ?string $errorMessage = null)
    {
        $this->reportedPlayers = $reportedPlayers;
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
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
