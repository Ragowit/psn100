<?php

declare(strict_types=1);

class ReportedPlayer
{
    private int $reportId;

    private string $onlineId;

    private string $explanation;

    public function __construct(int $reportId, string $onlineId, string $explanation)
    {
        $this->reportId = $reportId;
        $this->onlineId = $onlineId;
        $this->explanation = $explanation;
    }

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
