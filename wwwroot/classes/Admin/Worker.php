<?php

declare(strict_types=1);

final class Worker
{
    private int $id;

    private string $refreshToken;

    private string $npsso;

    private string $scanning;

    private DateTimeImmutable $scanStart;

    public function __construct(
        int $id,
        string $refreshToken,
        string $npsso,
        string $scanning,
        DateTimeImmutable $scanStart
    ) {
        $this->id = $id;
        $this->refreshToken = $refreshToken;
        $this->npsso = $npsso;
        $this->scanning = $scanning;
        $this->scanStart = $scanStart;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
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
}
