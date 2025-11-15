<?php

declare(strict_types=1);

final class PsnPlayerLookupRequestResult
{
    private string $normalizedOnlineId;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $result;

    private ?string $errorMessage;

    private ?string $decodedNpId;

    private ?string $npCountry;

    /**
     * @param array<string, mixed>|null $result
     */
    public function __construct(
        string $normalizedOnlineId,
        ?array $result,
        ?string $errorMessage,
        ?string $decodedNpId,
        ?string $npCountry
    ) {
        $this->normalizedOnlineId = $normalizedOnlineId;
        $this->result = $result;
        $this->errorMessage = $errorMessage;
        $this->decodedNpId = $decodedNpId;
        $this->npCountry = $npCountry;
    }

    public function getNormalizedOnlineId(): string
    {
        return $this->normalizedOnlineId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResult(): ?array
    {
        return $this->result;
    }

    public function hasResult(): bool
    {
        return $this->result !== null;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    public function getDecodedNpId(): ?string
    {
        return $this->decodedNpId;
    }

    public function getNpCountry(): ?string
    {
        return $this->npCountry;
    }
}
