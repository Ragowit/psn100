<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Homepage/HomepageItem.php';
require_once __DIR__ . '/../wwwroot/classes/Homepage/HomepageTitle.php';
require_once __DIR__ . '/../wwwroot/classes/Homepage/HomepageNewGame.php';
require_once __DIR__ . '/../wwwroot/classes/Homepage/HomepageDlc.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class HomepageItemTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     */
    private function createHomepageNewGame(array $overrides = []): HomepageNewGame
    {
        $defaults = [
            'id' => 123,
            'name' => 'Example Game',
            'icon_url' => 'example-icon.png',
            'platform' => 'PS4, PS5',
            'platinum' => 1,
            'gold' => 2,
            'silver' => 3,
            'bronze' => 4,
        ];

        return HomepageNewGame::fromArray(array_merge($defaults, $overrides));
    }

    public function testGetIconPathReturnsMissingPs5IconWhenIconMissingForPs5Title(): void
    {
        $item = $this->createHomepageNewGame([
            'icon_url' => '',
            'platform' => 'PS5',
        ]);

        $this->assertSame('/img/missing-ps5-game-and-trophy.png', $item->getIconPath());
    }

    public function testGetIconPathReturnsMissingPs4IconWhenIconMissingForNonPs5Title(): void
    {
        $item = $this->createHomepageNewGame([
            'icon_url' => '',
            'platform' => 'PS4',
        ]);

        $this->assertSame('/img/missing-ps4-game.png', $item->getIconPath());
    }

    public function testGetIconPathReturnsConfiguredIconWhenAvailable(): void
    {
        $item = $this->createHomepageNewGame([
            'icon_url' => 'cool-icon.png',
        ]);

        $this->assertSame('/img/title/cool-icon.png', $item->getIconPath());
    }

    public function testGetPlatformsSplitsTrimsAndFiltersValues(): void
    {
        $item = $this->createHomepageNewGame([
            'platform' => '  PS4 , PS5 , , PSVR2 ',
        ]);

        $this->assertSame(['PS4', 'PS5', 'PSVR2'], $item->getPlatforms());
    }

    public function testGetRelativeUrlUsesSlugifiedName(): void
    {
        $item = $this->createHomepageNewGame([
            'id' => 987,
            'name' => 'Ratchet & Clank: Rift Apart',
        ]);

        $utility = new Utility();

        $this->assertSame('/game/987-ratchet-and-clank-rift-apart', $item->getRelativeUrl($utility));
    }

    public function testHomepageDlcRelativeUrlAppendsGroupAnchor(): void
    {
        $dlc = HomepageDlc::fromArray([
            'id' => 42,
            'game_name' => "Marvel's Spider-Man",
            'group_id' => 'dlc-pack-1',
            'group_name' => 'The City That Never Sleeps',
            'icon_url' => 'dlc-icon.png',
            'platform' => 'PS4',
            'gold' => 1,
            'silver' => 2,
            'bronze' => 3,
        ]);

        $utility = new Utility();

        $this->assertSame(
            '/game/42-marvels-spiderman#dlc-pack-1',
            $dlc->getRelativeUrl($utility)
        );
    }
}
