<?php

declare(strict_types=1);

require_once __DIR__ . '/../Dto/PsnTrophySummaryDto.php';

final class PsnTrophySummaryMapper
{
    public function mapFromApiSummary(object $summary): PsnTrophySummaryDto
    {
        return new PsnTrophySummaryDto(
            (int) $summary->level(),
            (int) $summary->progress(),
            (int) $summary->platinum(),
            (int) $summary->gold(),
            (int) $summary->silver(),
            (int) $summary->bronze()
        );
    }
}
