<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerNavigationRenderer.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerNavigation.php';

final class PlayerNavigationRendererTest extends TestCase
{
    public function testRenderOutputsAllNavigationLinks(): void
    {
        $navigation = PlayerNavigation::forSection('Example User', PlayerNavigationSection::LOG);
        $renderer = new PlayerNavigationRenderer();

        $html = $renderer->render($navigation);
        $trimmedHtml = trim($html);

        $this->assertTrue(str_starts_with($trimmedHtml, '<div class="btn-group d-flex align-items-stretch">'));
        $this->assertSame(6, substr_count($html, '<a class="'));
        $this->assertStringContainsString('href="/player/Example%20User/log"', $html);
        $this->assertStringContainsString(
            'class="btn btn-primary active d-flex align-items-center justify-content-center"',
            $html
        );
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function testRenderEscapesAttributes(): void
    {
        $navigation = PlayerNavigation::forSection('user & user', PlayerNavigationSection::RANDOM);
        $renderer = new PlayerNavigationRenderer();

        $html = $renderer->render($navigation);

        $this->assertStringContainsString('href="/player/user%20%26%20user/random"', $html);
        $this->assertStringContainsString('user%20%26%20user', $html);
        $this->assertStringContainsString('Random Games', $html);
    }
}
