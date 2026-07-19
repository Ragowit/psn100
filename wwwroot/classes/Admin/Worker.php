<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerScanProgress.php';

final readonly class Worker
{
    public function __construct(
        final private int $id,
        #[\SensitiveParameter]
        final private string $refreshToken,
        #[\SensitiveParameter]
        final private string $npsso,
        final private string $scanning,
        final private DateTimeImmutable $scanStart,
        final private ?WorkerScanProgress $scanProgress,
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

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
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
