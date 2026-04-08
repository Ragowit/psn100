<?php

declare(strict_types=1);

final readonly class PsnGameLookupRequestResult
{
    /**
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        private string $normalizedGameId,
        private ?array $result,
        private ?string $errorMessage
    ) {
    }

    public function getNormalizedGameId(): string
    {
        return $this->normalizedGameId;
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
