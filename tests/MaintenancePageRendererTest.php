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
            '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" '
            . 'integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">',
            $html
        );
        $this->assertStringContainsString('Hello &amp; welcome!<br />
Line &lt;two&gt;', $html);
        $this->assertTrue(str_ends_with($html, "\n"), 'Rendered HTML should end with a newline.');
    }
}
