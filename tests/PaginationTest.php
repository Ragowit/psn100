<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Pagination.php';
require_once __DIR__ . '/../wwwroot/classes/PaginationItem.php';

final class PaginationTest extends TestCase
{
    public function testBuildItemsForMiddlePage(): void
    {
        $pagination = Pagination::create(3, 5);
        $items = $pagination->buildItems();

        $this->assertCount(7, $items);

        $renderedItems = array_map(
            static fn (PaginationItem $item): string => $item->render(
                static fn (int $page): string => '/page/' . $page
            ),
            $items
        );

        $this->assertSame(
            [
                '<li class="page-item"><a class="page-link" href="/page/2" aria-label="Previous">&lt;</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/1">1</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/2">2</a></li>',
                '<li class="page-item active"><a class="page-link" href="/page/3" aria-current="page">3</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/4">4</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/5">5</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/4" aria-label="Next">&gt;</a></li>',
            ],
            $renderedItems
        );
    }

    public function testBuildItemsAddsEllipsisWhenSkippingRanges(): void
    {
        $pagination = Pagination::create(8, 10);
        $items = $pagination->buildItems();

        $this->assertCount(9, $items);

        $renderedItems = array_map(
            static fn (PaginationItem $item): string => $item->render(
                static fn (int $page): string => '/page/' . $page
            ),
            $items
        );

        $this->assertSame(
            [
                '<li class="page-item"><a class="page-link" href="/page/7" aria-label="Previous">&lt;</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/1">1</a></li>',
                '<li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/6">6</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/7">7</a></li>',
                '<li class="page-item active"><a class="page-link" href="/page/8" aria-current="page">8</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/9">9</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/10">10</a></li>',
                '<li class="page-item"><a class="page-link" href="/page/9" aria-label="Next">&gt;</a></li>',
            ],
            $renderedItems
        );
    }

    public function testCreateNormalizesCurrentPageAndTotalPages(): void
    {
        $pagination = Pagination::create(-5, 0);
        $items = $pagination->buildItems();

        $this->assertCount(1, $items);

        $item = $items[0];
        $html = $item->render(static fn (int $page): string => '/page/' . $page);

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('>1<', $html);
    }
}
