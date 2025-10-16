<?php

declare(strict_types=1);

require_once __DIR__ . '/PossibleCheaterReport.php';
require_once __DIR__ . '/PossibleCheaterService.php';

class PossibleCheaterPage
{
    private PossibleCheaterReport $report;

    public function __construct(PossibleCheaterService $service)
    {
        $this->report = $service->createReport();
    }

    public function getReport(): PossibleCheaterReport
    {
        return $this->report;
    }
}
