<?php

declare(strict_types=1);

require_once __DIR__ . '/PossibleCheaterReport.php';
require_once __DIR__ . '/PossibleCheaterService.php';

class PossibleCheaterPage
{
    private PossibleCheaterReport $report;

    public function __construct(PossibleCheaterService $service)
    {
        $generalCheaters = array_map(
            static fn(array $cheater): PossibleCheaterReportEntry => PossibleCheaterReportEntry::fromArray($cheater),
            $service->getGeneralPossibleCheaters()
        );

        $sections = array_map(
            static fn(array $section): PossibleCheaterReportSection => PossibleCheaterReportSection::fromArray($section),
            $service->getSectionResults()
        );

        $this->report = new PossibleCheaterReport($generalCheaters, $sections);
    }

    public function getReport(): PossibleCheaterReport
    {
        return $this->report;
    }
}
