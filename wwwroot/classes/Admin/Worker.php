<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerScanProgress.php';

final readonly class Worker
{
    public function __construct(
        private int $id,
        private string $npsso,
        private string $scanning,
        private DateTimeImmutable $scanStart,
        private ?WorkerScanProgress $scanProgress,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNpsso(): string
    {
        return $this->npsso;
    }

    public function getScanning(): string
    {
        return $this->scanning;
    }

    public function getScanStart(): DateTimeImmutable
    {
        return $this->scanStart;
    }

    public function getScanProgress(): ?WorkerScanProgress
    {
        return $this->scanProgress;
    }
}
