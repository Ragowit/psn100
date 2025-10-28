<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/ChangelogPaginator.php';

final class ChangelogPaginatorTest extends TestCase
{
    public function testPaginationCalculatesValuesForMiddlePage(): void
    {
        $paginator = new ChangelogPaginator(2, 55, 10);

        $this->assertSame(2, $paginator->getCurrentPage());
        $this->assertSame(10, $paginator->getLimit());
        $this->assertSame(55, $paginator->getTotalCount());
        $this->assertSame(6, $paginator->getTotalPages());
        $this->assertTrue($paginator->hasResults());
        $this->assertSame(10, $paginator->getOffset());
        $this->assertSame(11, $paginator->getRangeStart());
        $this->assertSame(20, $paginator->getRangeEnd());
        $this->assertTrue($paginator->hasPreviousPage());
        $this->assertTrue($paginator->hasNextPage());
        $this->assertSame(1, $paginator->getPreviousPage());
        $this->assertSame(3, $paginator->getNextPage());
        $this->assertSame(6, $paginator->getLastPageNumber());
    }

    public function testRequestedPageIsClampedWithinValidRange(): void
    {
        $paginator = new ChangelogPaginator(99, 12, 5);

        $this->assertSame(3, $paginator->getTotalPages());
        $this->assertSame(3, $paginator->getCurrentPage());
        $this->assertSame(11, $paginator->getRangeStart());
        $this->assertSame(12, $paginator->getRangeEnd());
        $this->assertFalse($paginator->hasNextPage());
        $this->assertSame(3, $paginator->getLastPageNumber());
        $this->assertSame(2, $paginator->getPreviousPage());
    }

    public function testHandlesRequestedPageBelowOne(): void
    {
        $paginator = new ChangelogPaginator(-5, 20, 7);

        $this->assertSame(1, $paginator->getCurrentPage());
        $this->assertSame(7, $paginator->getLimit());
        $this->assertSame(3, $paginator->getTotalPages());
        $this->assertSame(1, $paginator->getRangeStart());
        $this->assertSame(7, $paginator->getRangeEnd());
        $this->assertFalse($paginator->hasPreviousPage());
        $this->assertTrue($paginator->hasNextPage());
        $this->assertSame(2, $paginator->getNextPage());
    }

    public function testHandlesEmptyOrInvalidTotalCount(): void
    {
        $paginator = new ChangelogPaginator(1, -10, 0);

        $this->assertSame(1, $paginator->getCurrentPage());
        $this->assertSame(0, $paginator->getTotalCount());
        $this->assertSame(1, $paginator->getLimit());
        $this->assertSame(0, $paginator->getTotalPages());
        $this->assertFalse($paginator->hasResults());
        $this->assertSame(0, $paginator->getRangeStart());
        $this->assertSame(0, $paginator->getRangeEnd());
        $this->assertSame(0, $paginator->getOffset());
        $this->assertFalse($paginator->hasPreviousPage());
        $this->assertFalse($paginator->hasNextPage());
        $this->assertSame(1, $paginator->getLastPageNumber());
    }
}
