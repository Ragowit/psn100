<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerRandomGame.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class PlayerRandomGameTest extends TestCase
{
    private Utility $utility;

    protected function setUp(): void
    {
        $this->utility = new Utility();
    }

    public function testGetPlatformsSplitsAndTrimsPlatformList(): void
    {
        $game = new PlayerRandomGame([
            'id' => 42,
            'name' => 'Example Game',
            'platform' => 'PS4, PS5 , , PSVITA ,,PSVR2,',
        ], $this->utility);

        $this->assertSame(
            ['PS4', 'PS5', 'PSVITA', 'PSVR2'],
            $game->getPlatforms()
        );
    }

    public function testGetPlatformsReturnsEmptyArrayWhenPlatformMissing(): void
    {
        $game = new PlayerRandomGame([
            'id' => 42,
            'name' => 'Example Game',
            'platform' => '',
        ], $this->utility);

        $this->assertSame([], $game->getPlatforms());

        $gameWithoutPlatformKey = new PlayerRandomGame([
            'id' => 42,
            'name' => 'Example Game',
        ], $this->utility);

        $this->assertSame([], $gameWithoutPlatformKey->getPlatforms());
    }

    public function testGetIconUrlReturnsPlaceholderWhenIconMissingForPlayStation5Families(): void
    {
        $gamePs5 = new PlayerRandomGame([
            'id' => 1,
            'name' => 'Game',
            'icon_url' => '.png',
            'platform' => 'PS5, PS4',
        ], $this->utility);

        $this->assertSame('../missing-ps5-game-and-trophy.png', $gamePs5->getIconUrl());

        $gamePsvr2 = new PlayerRandomGame([
            'id' => 2,
            'name' => 'Game',
            'icon_url' => '.png',
            'platform' => 'PSVR2',
        ], $this->utility);

        $this->assertSame('../missing-ps5-game-and-trophy.png', $gamePsvr2->getIconUrl());

        $gamePs4 = new PlayerRandomGame([
            'id' => 3,
            'name' => 'Game',
            'icon_url' => '.png',
            'platform' => 'PS4',
        ], $this->utility);

        $this->assertSame('../missing-ps4-game.png', $gamePs4->getIconUrl());

        $gameWithIcon = new PlayerRandomGame([
            'id' => 4,
            'name' => 'Game',
            'icon_url' => 'https://example.com/icon.png',
            'platform' => 'PS5',
        ], $this->utility);

        $this->assertSame('https://example.com/icon.png', $gameWithIcon->getIconUrl());
    }

    public function testGetGameLinkIncludesSlugifiedNameAndUrlEncodedPlayerId(): void
    {
        $game = new PlayerRandomGame([
            'id' => 321,
            'name' => 'Ratchet & Clank: Rift Apart',
        ], $this->utility);

        $this->assertSame(
            '321-ratchet-and-clank-rift-apart/Player%20One',
            $game->getGameLink('Player One')
        );

        $this->assertSame(
            '321-ratchet-and-clank-rift-apart',
            $game->getGameLink('')
        );
    }
}
