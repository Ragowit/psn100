<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PaginationItem.php';

final class PaginationItemTest extends TestCase
{
    public function testRenderIncludesAriaAttributesWhenActive(): void
    {
        $item = PaginationItem::forPage(2, 'Page 2')
            ->markAsActive()
            ->setAriaLabel('Current Page');

        $html = $item->render(static fn (int $page): string => '/page/' . $page);

        $this->assertStringContainsString('class="page-item active"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('aria-label="Current Page"', $html);
        $this->assertStringContainsString('href="/page/2"', $html);
    }

    public function testRenderEscapesLabelAndUrl(): void
    {
        $item = PaginationItem::forPage(5, '<Next>')
            ->setAriaLabel('Go to >');

        $html = $item->render(static fn (int $page): string => '/page/' . $page . '?q=<script>');

        $this->assertStringContainsString('&lt;Next&gt;', $html);
        $this->assertStringContainsString('href="/page/5?q=&lt;script&gt;"', $html);
        $this->assertStringContainsString('aria-label="Go to &gt;"', $html);
    }

    public function testEllipsisIsDisabledAndDoesNotGenerateUrl(): void
    {
        $item = PaginationItem::ellipsis();

        $html = $item->render(static fn (int $page): string => '/page/' . $page);

        $this->assertStringContainsString('class="page-item disabled"', $html);
        $this->assertStringContainsString('href="#"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('aria-disabled="true"', $html);
    }
}
