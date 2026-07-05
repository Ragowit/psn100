<?php

declare(strict_types=1);

final readonly class PlayerReportDeletionRequest
{
    private function __construct(
        private ?int $deleteId,
        private ?string $errorMessage
    ) {
    }

    /**
     * @param array<string, mixed> $postData
     */
    public static function fromPostData(array $postData): self
    {
        if (!array_key_exists('delete_id', $postData)) {
            return new self(null, null);
        }

        $deleteId = self::parsePositiveInt($postData['delete_id'] ?? null);

        if ($deleteId === null) {
            return new self(null, 'Please provide a valid report ID to delete.');
        }

        return new self($deleteId, null);
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function hasDeletionRequest(): bool
    {
        return $this->deleteId !== null || $this->errorMessage !== null;
    }

    public function isValidDeletion(): bool
    {
        return $this->deleteId !== null && !$this->hasError();
    }

    public function getDeleteId(): ?int
    {
        return $this->deleteId;
    }

    private static function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }

        $intValue = (int) $trimmed;

        return $intValue > 0 ? $intValue : null;
    }
}
