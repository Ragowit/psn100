<?php

declare(strict_types=1);

final readonly class WorkerScanProgress
{
    private function __construct(
        final private ?int $current,
        final private ?int $total,
        final private ?string $title,
        final private ?string $npCommunicationId,
    ) {
    }

    #[\NoDiscard]
    public static function fromJson(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return self::fromArray($decoded);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public static function fromArray(array $data): ?self
    {
        $current = self::sanitizeInt($data['current'] ?? null);
        $total = self::sanitizeInt($data['total'] ?? null);
        $title = self::sanitizeString($data['title'] ?? null);
        $npCommunicationId = self::sanitizeString($data['npCommunicationId'] ?? null);

        if ($current === null && $total === null && $title === null && $npCommunicationId === null) {
            return null;
        }

        return new self($current, $total, $title, $npCommunicationId);
    }

    public function getCurrent(): ?int
    {
        return $this->current;
    }

    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getNpCommunicationId(): ?string
    {
        return $this->npCommunicationId;
    }

    public function getProgressSummary(): ?string
    {
        return match (true) {
            $this->current !== null && $this->total !== null && $this->total > 0
                => sprintf('%d / %d', $this->current, $this->total),
            $this->current !== null && $this->total === null
                => (string) $this->current,
            $this->current === null && $this->total !== null
                => sprintf('0 / %d', $this->total),
            default => null,
        };
    }

    public function getPercentage(): ?float
    {
        if ($this->current === null || $this->total === null || $this->total <= 0) {
            return null;
        }

        return round(($this->current / $this->total) * 100, 1);
    }

    private static function sanitizeInt(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    private static function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
