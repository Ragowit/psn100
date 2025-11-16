<?php

declare(strict_types=1);

final class PlayerScanProgress
{
    private ?int $current;

    private ?int $total;

    private ?string $title;

    private ?string $npCommunicationId;

    private function __construct(?int $current, ?int $total, ?string $title, ?string $npCommunicationId)
    {
        $this->current = $current;
        $this->total = $total;
        $this->title = $title;
        $this->npCommunicationId = $npCommunicationId;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }

        $current = self::sanitizeInt($data['current'] ?? null);
        $total = self::sanitizeInt($data['total'] ?? null);
        $title = self::sanitizeString($data['title'] ?? null);
        $npCommunicationId = self::sanitizeString($data['npCommunicationId'] ?? null);

        if ($current === null && $total === null && $title === null && $npCommunicationId === null) {
            return null;
        }

        return new self($current, $total, $title, $npCommunicationId);
    }

    public static function fromJson(?string $json): ?self
    {
        if ($json === null) {
            return null;
        }

        $trimmedJson = trim($json);

        if ($trimmedJson === '') {
            return null;
        }

        try {
            $decoded = json_decode($trimmedJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return self::fromArray($decoded);
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

    public function hasCounts(): bool
    {
        return $this->current !== null && $this->total !== null;
    }

    public function getProgressSummary(): ?string
    {
        if (!$this->hasCounts() || $this->total === null || $this->total === 0) {
            return null;
        }

        $current = min($this->current ?? 0, $this->total);

        return sprintf('(%d/%d)', $current, $this->total);
    }

    public function getPercentage(): ?int
    {
        if (!$this->hasCounts() || $this->total === null || $this->total === 0) {
            return null;
        }

        $current = min($this->current ?? 0, $this->total);
        $percentage = (int) round(($current / $this->total) * 100);

        return max(0, min(100, $percentage));
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
