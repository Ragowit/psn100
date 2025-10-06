<?php

declare(strict_types=1);

require_once __DIR__ . '/GameDetail.php';

class GameDetailPageResult
{
    private ?GameDetail $gameDetail;

    private ?string $successMessage;

    private ?string $errorMessage;

    public function __construct(?GameDetail $gameDetail, ?string $successMessage, ?string $errorMessage)
    {
        $this->gameDetail = $gameDetail;
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
    }

    public function getGameDetail(): ?GameDetail
    {
        return $this->gameDetail;
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
