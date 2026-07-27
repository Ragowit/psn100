<?php

declare(strict_types=1);

require_once __DIR__ . '/PossibleCheaterReport.php';
require_once __DIR__ . '/PossibleCheaterService.php';

final readonly class PossibleCheaterPage
{
    private function __construct(final private PossibleCheaterReport $report)
    {
    }

    #[\NoDiscard]
    public static function fromService(PossibleCheaterService $service): self
    {
        return new self($service->createReport());
    }

    public function getReport(): PossibleCheaterReport
    {
        return $this->report;
    }
}
