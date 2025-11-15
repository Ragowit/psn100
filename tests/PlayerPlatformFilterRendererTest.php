<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerPlatformFilterRenderer.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerPlatformFilterOptions.php';

final class PlayerPlatformFilterRendererTest extends TestCase
{
    public function testRenderIncludesCheckedAttributeForSelectedPlatforms(): void
    {
        $options = PlayerPlatformFilterOptions::fromSelectionCallback(
            static fn (string $platform): bool => $platform === 'ps5'
        );
        $renderer = new PlayerPlatformFilterRenderer();

        $html = $renderer->render($options);

        $this->assertStringContainsString('name="ps5"', $html);
        $this->assertStringContainsString('id="filterPS5"', $html);
        $this->assertStringContainsString('type="checkbox" checked', $html);
    }

    public function testRenderEscapesButtonLabel(): void
    {
        $options = PlayerPlatformFilterOptions::fromSelectionCallback(static fn (): bool => false);
        $renderer = new PlayerPlatformFilterRenderer('<Filter>');

        $html = $renderer->render($options);

        $this->assertStringContainsString('&lt;Filter&gt;', $html);
    }

    public function testRenderIncludesAllPlatformLabels(): void
    {
        $options = PlayerPlatformFilterOptions::fromSelectionCallback(static fn (): bool => false);
        $renderer = PlayerPlatformFilterRenderer::createDefault();

        $html = $renderer->render($options);

        foreach ($options->getOptions() as $option) {
            $this->assertStringContainsString(
                htmlspecialchars($option->getLabel(), ENT_QUOTES, 'UTF-8'),
                $html
            );
        }
    }
}
