<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PaginationRenderer.php';
require_once __DIR__ . '/../wwwroot/classes/Pagination.php';
require_once __DIR__ . '/../wwwroot/classes/PaginationItem.php';

final class PaginationRendererTest extends TestCase
{
    public function testRenderGeneratesBootstrapPaginationHtml(): void
    {
        $renderer = new PaginationRenderer();

        $html = $renderer->render(
            2,
            4,
            static fn (int $page): array => [
                'page' => $page,
                'sort' => 'name',
            ],
            'Player navigation'
        );

        $this->assertStringContainsString('<nav aria-label="Player navigation">', $html);
        $this->assertStringContainsString('<ul class="pagination justify-content-center">', $html);
        $this->assertStringContainsString('href="?page=1&amp;sort=name"', $html);
        $this->assertStringContainsString('href="?page=2&amp;sort=name"', $html);
        $this->assertStringContainsString('href="?page=3&amp;sort=name"', $html);
        $this->assertStringContainsString('href="?page=4&amp;sort=name"', $html);
    }

    public function testRenderEscapesAriaLabelAndQueryParameters(): void
    {
        $renderer = new PaginationRenderer();

        $html = $renderer->render(
            1,
            1,
            static fn (int $page): array => [
                'page' => $page,
                'filter' => '<active>',
            ],
            'Browse > players'
        );

        $this->assertStringContainsString('aria-label="Browse &gt; players"', $html);
        $this->assertStringContainsString('href="?page=1&amp;filter=%3Cactive%3E"', $html);
    }

    public function testRenderThrowsWhenQueryParametersFactoryReturnsNonArray(): void
    {
        $renderer = new PaginationRenderer();

        try {
            $renderer->render(
                1,
                1,
                static fn (int $page): string => 'page=' . $page,
                null
            );
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('must return an array', $exception->getMessage());

            return;
        }

        $this->fail('Expected InvalidArgumentException to be thrown when the query parameter factory returns a non-array.');
    }
}
