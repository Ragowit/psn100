<?php

declare(strict_types=1);

final readonly class LogDeletionRequest
{
    private const string ACTION_SINGLE = 'single';
    private const string ACTION_BULK = 'bulk';

    /**
     * @param list<int> $bulkDeletionIds
     */
    private function __construct(
        final private ?string $action,
        final private ?int $singleDeletionId,
        /** @var list<int> */
        final private array $bulkDeletionIds,
        final private ?string $errorMessage
    ) {
    }

    /**
     * @param array<string, mixed> $postData
     */
    #[\NoDiscard]
    public static function fromPostData(array $postData): self
    {
        $action = null;
        $singleDeletionId = null;
        $bulkDeletionIds = [];
        $errorMessage = null;

        if (array_key_exists('delete_id', $postData)) {
            $action = self::ACTION_SINGLE;
            $singleDeletionId = self::parsePositiveInt($postData['delete_id'] ?? null);

            if ($singleDeletionId === null) {
                $errorMessage = 'Please provide a valid log entry ID to delete.';
            }
        } elseif (array_key_exists('delete_selected', $postData) || array_key_exists('delete_ids', $postData)) {
            $action = self::ACTION_BULK;
            $bulkDeletionIds = self::parsePositiveIntList($postData['delete_ids'] ?? []);

            if ($bulkDeletionIds === []) {
                $errorMessage = 'Please select at least one log entry to delete.';
            }
        }

        return new self($action, $singleDeletionId, $bulkDeletionIds, $errorMessage);
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
        return $this->action !== null;
    }

    public function isSingleDeletion(): bool
    {
        return $this->action === self::ACTION_SINGLE && !$this->hasError();
    }

    public function isBulkDeletion(): bool
    {
        return $this->action === self::ACTION_BULK && !$this->hasError();
    }

    public function getSingleDeletionId(): ?int
    {
        return $this->singleDeletionId;
    }

    /**
     * @return list<int>
     */
    public function getBulkDeletionIds(): array
    {
        return $this->bulkDeletionIds;
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

    /**
     * @param mixed $values
     * @return list<int>
     */
    private static function parsePositiveIntList(mixed $values): array
    {
        if (!is_iterable($values)) {
            return [];
        }

        $uniqueValues = [];

        foreach ($values as $value) {
            $parsed = self::parsePositiveInt($value);

            if ($parsed === null) {
                continue;
            }

            $uniqueValues[$parsed] = $parsed;
        }

        if ($uniqueValues !== []) {
            ksort($uniqueValues);
        }

        return array_values($uniqueValues);
    }
}
