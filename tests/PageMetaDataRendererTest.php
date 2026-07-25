<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PageMetaData.php';
require_once __DIR__ . '/../wwwroot/classes/PageMetaDataRenderer.php';

final class PageMetaDataRendererTest extends TestCase
{
    private PageMetaDataRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PageMetaDataRenderer();
    }

    public function testRenderReturnsEmptyStringWhenMetaDataIsEmpty(): void
    {
        $result = $this->renderer->render(new PageMetaData);

        $this->assertSame('', $result);
    }

    public function testRenderProducesExpectedTagsWithEscapedValues(): void
    {
        $metaData = (new PageMetaData)
            ->withTitle('Title "special" & more')
            ->withDescription("Description with 'quote' & <tag>")
            ->withImage('https://example.com/image.png?foo=1&bar=2')
            ->withUrl('https://example.com/page?foo=bar&baz=<baz>');

        $result = $this->renderer->render($metaData);

        $expected = implode(PHP_EOL, [
            '<link rel="canonical" href="https://example.com/page?foo=bar&amp;baz=&lt;baz&gt;" />',
            '<meta property="og:url" content="https://example.com/page?foo=bar&amp;baz=&lt;baz&gt;">',
            '<meta property="og:description" content="Description with &#039;quote&#039; &amp; &lt;tag&gt;">',
            '<meta property="og:image" content="https://example.com/image.png?foo=1&amp;bar=2">',
            '<meta property="og:title" content="Title &quot;special&quot; &amp; more">',
            '<meta name="twitter:image:alt" content="Title &quot;special&quot; &amp; more">',
            '<meta property="og:site_name" content="PSN 100%">',
            '<meta property="og:type" content="article">',
            '<meta name="twitter:card" content="summary_large_image">',
        ]);

        $this->assertSame($expected, $result);
    }

    public function testRenderMakesRelativeImageAndUrlAbsoluteForOpenGraph(): void
    {
        $metaData = (new PageMetaData)
            ->withTitle('Relative Meta')
            ->withImage('/img/title/my icon.png')
            ->withUrl('/game/1-example');

        $result = $this->renderer->render($metaData);

        $this->assertStringContainsString(
            '<link rel="canonical" href="https://psn100.net/game/1-example" />',
            $result
        );
        $this->assertStringContainsString(
            '<meta property="og:url" content="https://psn100.net/game/1-example">',
            $result
        );
        $this->assertStringContainsString(
            '<meta property="og:image" content="https://psn100.net/img/title/my icon.png">',
            $result
        );
    }
}
