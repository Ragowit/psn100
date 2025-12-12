<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerGamesPageContext.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerGamesFilter.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerSummary.php';

final class PlayerGamesPageContextPageStub extends PlayerGamesPage
{
    public function __construct()
    {
        // Intentionally bypass parent construction for testing purposes.
    }
}

final class PlayerGamesPageContextTest extends TestCase
{
    public function testContextProvidesNavigationAndPlatformOptions(): void
    {
        $context = $this->createContext();

        $this->assertSame('SampleUser', $context->getPlayerOnlineId());
        $this->assertSame(123, $context->getPlayerAccountId());
        $this->assertTrue($context->shouldDisplayGames());
        $this->assertFalse($context->isPlayerFlagged());
        $this->assertFalse($context->isPlayerPrivate());

        $navigation = $context->getPlayerNavigation();
        $this->assertTrue($navigation instanceof PlayerNavigation);

        $platformOptions = $context->getPlatformFilterOptions();
        $selectedPlatforms = array_filter(
            $platformOptions->getOptions(),
            static fn (PlayerPlatformFilterOption $option): bool => $option->isSelected()
        );

        $this->assertCount(1, $selectedPlatforms);
        $this->assertSame('PS5', array_values($selectedPlatforms)[0]->getLabel());
    }

    public function testContextReflectsPlayerStatus(): void
    {
        $flagged = $this->createContext([], PlayerStatus::FLAGGED);
        $this->assertTrue($flagged->isPlayerFlagged());
        $this->assertFalse($flagged->shouldDisplayGames());

        $private = $this->createContext([], PlayerStatus::PRIVATE);
        $this->assertTrue($private->isPlayerPrivate());
        $this->assertFalse($private->shouldDisplayGames());

        $public = $this->createContext([], PlayerStatus::NORMAL);
        $this->assertFalse($public->isPlayerFlagged());
        $this->assertFalse($public->isPlayerPrivate());
        $this->assertTrue($public->shouldDisplayGames());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createContext(array $overrides = [], PlayerStatus $status = PlayerStatus::NORMAL): PlayerGamesPageContext
    {
        $playerData = array_merge(
            [
                'online_id' => 'SampleUser',
                'avatar_url' => 'avatar.png',
                'level' => 100,
                'progress' => 55,
                'platinum' => 25,
            ],
            $overrides
        );

        $page = new PlayerGamesPageContextPageStub();
        $filter = PlayerGamesFilter::fromArray(['ps5' => 'true']);
        $summary = new PlayerSummary(25, 10, 50.0, 42);

        return PlayerGamesPageContext::fromComponents(
            $page,
            $summary,
            $filter,
            $playerData,
            123,
            $status
        );
    }
}
