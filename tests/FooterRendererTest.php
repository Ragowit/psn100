<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/FooterRenderer.php';
require_once __DIR__ . '/../wwwroot/classes/FooterViewModel.php';

final class FooterRendererTest extends TestCase
{
    public function testRenderOutputsFooterHtmlWithEscapedValues(): void
    {
        $viewModel = new FooterViewModel(
            2020,
            2023,
            'v1.0 "alpha"',
            'https://example.com/releases?tag="latest"',
            '"/changelog"',
            'https://example.com/issues?issue=<1>',
            'Rago & Co',
            '"/player/<Rago>"',
            "https://example.com/contributors?name='Rago'"
        );

        $renderer = new FooterRenderer();

        $html = $renderer->render($viewModel);

        $this->assertStringContainsString('<footer class="container">', $html);
        $this->assertStringContainsString('&copy; 2020-2023', $html);
        $this->assertStringContainsString('href="https://example.com/releases?tag=&quot;latest&quot;"', $html);
        $this->assertStringContainsString('>v1.0 &quot;alpha&quot;</a>', $html);
        $this->assertStringContainsString('href="&quot;/changelog&quot;"', $html);
        $this->assertStringContainsString('href="https://example.com/issues?issue=&lt;1&gt;"', $html);
        $this->assertStringContainsString('>Rago &amp; Co</a>', $html);
        $this->assertStringContainsString('href="&quot;/player/&lt;Rago&gt;&quot;"', $html);
        $this->assertStringContainsString('href="https://example.com/contributors?name=&#039;Rago&#039;"', $html);
        $this->assertStringContainsString('PSN100 is not affiliated with Sony or PlayStation in any way.', $html);
    }
}
