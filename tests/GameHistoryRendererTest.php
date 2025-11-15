<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameHistoryRenderer.php';
require_once __DIR__ . '/../wwwroot/classes/Game/GameDetails.php';

final class GameHistoryRendererTest extends TestCase
{
    public function testRenderTextDiffHighlightsChanges(): void
    {
        $renderer = new GameHistoryRenderer();
        $diff = [
            'previous' => 'Old name',
            'current' => 'New name',
        ];

        $html = $renderer->renderTextDiff($diff);

        $this->assertStringContainsString('history-diff', $html);
        $this->assertStringContainsString('history-highlight--removed', $html);
        $this->assertStringContainsString('history-highlight--added', $html);
        $this->assertStringContainsString('Old', $html);
        $this->assertStringContainsString('New', $html);
    }

    public function testRenderNumberDiffWrapsValuesInBlocks(): void
    {
        $renderer = new GameHistoryRenderer();
        $diff = [
            'previous' => 5,
            'current' => 10,
        ];

        $html = $renderer->renderNumberDiff($diff);

        $this->assertStringContainsString('history-diff__previous', $html);
        $this->assertStringContainsString('history-diff__current', $html);
        $this->assertStringContainsString('5', $html);
        $this->assertStringContainsString('10', $html);
    }

    public function testRenderIconDiffUsesMissingAssetFallback(): void
    {
        $renderer = new GameHistoryRenderer();
        $game = $this->createGameDetails();

        $html = $renderer->renderIconDiff([
            'previous' => '.png',
            'current' => '.png',
        ], $game, 'title', 'Sample');

        $this->assertStringContainsString('missing-ps5-game-and-trophy.png', $html);
    }

    public function testRenderSingleIconOutputsPlaceholderForNull(): void
    {
        $renderer = new GameHistoryRenderer();
        $game = $this->createGameDetails();

        $html = $renderer->renderSingleIcon(null, $game, 'trophy', 'Reward');

        $this->assertStringContainsString('history-diff__empty', $html);
    }

    private function createGameDetails(): GameDetails
    {
        return GameDetails::fromArray([
            'id' => 1,
            'name' => 'Sample Game',
            'np_communication_id' => 'NPWR00001',
            'parent_np_communication_id' => null,
            'platform' => 'PS5',
            'icon_url' => 'icon.png',
            'set_version' => '01.00',
            'region' => 'US',
            'message' => null,
            'platinum' => 1,
            'gold' => 2,
            'silver' => 3,
            'bronze' => 4,
            'owners_completed' => 5,
            'owners' => 10,
            'difficulty' => '5',
            'status' => 0,
            'rarity_points' => 100,
            'obsolete_ids' => '',
        ]);
    }
}
