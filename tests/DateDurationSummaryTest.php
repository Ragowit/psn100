<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/DateDurationSummary.php';

final class DateDurationSummaryTest extends TestCase
{
    public function testSignificantPartsReturnsLeadingNonZeroUnits(): void
    {
        $start = new DateTimeImmutable('2020-01-01 00:00:00');
        $end = new DateTimeImmutable('2021-03-05 06:07:08');

        $this->assertSame(
            ['1 years', '2 months'],
            DateDurationSummary::significantParts($start, $end)
        );
    }

    public function testSignificantPartsRespectsMaxParts(): void
    {
        $start = new DateTimeImmutable('2020-01-01 00:00:00');
        $end = new DateTimeImmutable('2020-01-05 01:00:00');

        $this->assertSame(
            ['4 days'],
            DateDurationSummary::significantParts($start, $end, 1)
        );
    }

    public function testSignificantPartsReturnsEmptyForZeroInterval(): void
    {
        $moment = new DateTimeImmutable('2020-01-01 00:00:00');

        $this->assertSame([], DateDurationSummary::significantParts($moment, $moment));
    }
}
