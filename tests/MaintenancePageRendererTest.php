<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/MaintenancePageRenderer.php';

final class MaintenancePageRendererTest extends TestCase
{
    public function testRenderEscapesContentAndFormatsMessage(): void
    {
        $page = MaintenancePage::createDefault()->withMessage("Hello & welcome!\nLine <two>");
        $renderer = new MaintenancePageRenderer();

        $html = $renderer->render($page);

        $this->assertStringContainsString('<!doctype html>', $html);
        $this->assertStringContainsString('<title>Maintenance ~ PSN 100%</title>', $html);
        $this->assertStringContainsString(
            '<meta name="description" content="Check your leaderboard position against other PlayStation trophy hunters!">',
            $html
        );
        $this->assertStringContainsString(
            '<link rel="stylesheet" href="/lib/bootstrap/5.3.8/css/bootstrap.min.css?v=',
            $html
        );
        $this->assertFalse(str_contains($html, 'integrity='));
        $this->assertFalse(str_contains($html, 'crossorigin='));
        $this->assertStringContainsString('Hello &amp; welcome!<br />
Line &lt;two&gt;', $html);
        $this->assertTrue(str_ends_with($html, "\n"), 'Rendered HTML should end with a newline.');
    }
}
