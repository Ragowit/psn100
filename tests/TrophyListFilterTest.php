<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyListFilter.php';

final class TrophyListFilterTest extends TestCase
{
    public function testConstructorNormalizesPageToMinimumOfOne(): void
    {
        $zeroPageFilter = new TrophyListFilter(0);
        $negativePageFilter = new TrophyListFilter(-10);

        $this->assertSame(1, $zeroPageFilter->getPage());
        $this->assertSame(1, $negativePageFilter->getPage());
    }

    public function testFromArrayParsesNumericPageValues(): void
    {
        $filter = TrophyListFilter::fromArray(['page' => '3']);

        $this->assertSame(3, $filter->getPage());
    }

    public function testFromArrayDefaultsToFirstPageWhenInvalid(): void
    {
        $filter = TrophyListFilter::fromArray(['page' => 'not-a-number']);

        $this->assertSame(1, $filter->getPage());
    }

    public function testGetOffsetUsesCurrentPage(): void
    {
        $filter = new TrophyListFilter(4);

        $this->assertSame(75, $filter->getOffset(25));
    }

    public function testToQueryParametersIncludesNormalizedPage(): void
    {
        $filter = new TrophyListFilter(-2);

        $this->assertSame(['page' => 1], $filter->toQueryParameters());
    }

    public function testWithPageMergesFilterParameters(): void
    {
        $filter = new class(2) extends TrophyListFilter {
            public function getFilterParameters(): array
            {
                return ['foo' => 42];
            }
        };

        $this->assertSame(['foo' => 42, 'page' => 5], $filter->withPage(5));
        $this->assertSame(['foo' => 42, 'page' => 1], $filter->withPage(0));
    }
}
