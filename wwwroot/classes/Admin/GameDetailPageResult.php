<?php

declare(strict_types=1);

require_once __DIR__ . '/GameDetail.php';

final readonly class GameDetailPageResult
{
    public function __construct(
        final private ?GameDetail $gameDetail,
        final private ?string $successMessage,
        final private ?string $errorMessage,
    ) {
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
