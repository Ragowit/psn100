<?php

declare(strict_types=1);

final class WorkerScanProgress
{
    private ?int $current;

    private ?int $total;

    private ?string $title;

    private ?string $npCommunicationId;

    private function __construct(
        ?int $current,
        ?int $total,
        ?string $title,
        ?string $npCommunicationId
    ) {
        $this->current = $current;
        $this->total = $total;
        $this->title = $title;
        $this->npCommunicationId = $npCommunicationId;
    }

    public static function fromJson(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return null;
        }

        return self::fromArray($decoded);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $current = array_key_exists('current', $data) && is_numeric($data['current'])
            ? max(0, (int) $data['current'])
            : null;

        $total = array_key_exists('total', $data) && is_numeric($data['total'])
            ? max(0, (int) $data['total'])
            : null;

        $title = array_key_exists('title', $data) && is_string($data['title']) && $data['title'] !== ''
            ? $data['title']
            : null;

        $npCommunicationId = array_key_exists('npCommunicationId', $data)
            && is_string($data['npCommunicationId'])
            && $data['npCommunicationId'] !== ''
            ? $data['npCommunicationId']
            : null;

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
        if ($this->current !== null && $this->total !== null) {
            if ($this->total > 0) {
                return sprintf('%d / %d', $this->current, $this->total);
            }

            return null;
        }

        if ($this->current !== null && $this->total === null) {
            return (string) $this->current;
        }

        if ($this->total !== null && $this->current === null) {
            return sprintf('0 / %d', $this->total);
        }

        return null;
    }

    public function getPercentage(): ?float
    {
        if ($this->current === null || $this->total === null || $this->total <= 0) {
            return null;
        }

        return round(($this->current / $this->total) * 100, 1);
    }
}
