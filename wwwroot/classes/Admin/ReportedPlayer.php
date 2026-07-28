<?php

declare(strict_types=1);

final readonly class ReportedPlayer
{
    public function __construct(
        final private int $reportId,
        final private string $onlineId,
        final private string $explanation
    ) {}

    public function getReportId(): int
    {
        return $this->reportId;
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }
}
