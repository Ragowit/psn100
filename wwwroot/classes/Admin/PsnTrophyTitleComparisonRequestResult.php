<?php

declare(strict_types=1);

final readonly class PsnTrophyTitleComparisonRequestResult
{
    /**
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        private string $normalizedAccountId,
        private ?array $result,
        private ?string $errorMessage,
    ) {
    }

    public function getNormalizedAccountId(): string
    {
        return $this->normalizedAccountId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
