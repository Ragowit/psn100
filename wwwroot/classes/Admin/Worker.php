<?php

declare(strict_types=1);

final class Worker
{
    private int $id;

    private string $npsso;

    private string $scanning;

    private DateTimeImmutable $scanStart;

    /**
     * @var array{current?: int, total?: int, title?: string, npCommunicationId?: string}|null
     */
    private ?array $scanProgress;

    public function __construct(
        int $id,
        string $npsso,
        string $scanning,
        DateTimeImmutable $scanStart,
        ?array $scanProgress
    ) {
        $this->id = $id;
        $this->npsso = $npsso;
        $this->scanning = $scanning;
        $this->scanStart = $scanStart;
        $this->scanProgress = $scanProgress;
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

    /**
     * @return array{current?: int, total?: int, title?: string, npCommunicationId?: string}|null
     */
    public function getScanProgress(): ?array
    {
        return $this->scanProgress;
    }
}
