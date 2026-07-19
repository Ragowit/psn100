<?php

declare(strict_types=1);

final readonly class ReportedPlayer
{
    public function __construct(
        private int $reportId,
        private string $onlineId,
        private string $explanation
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
