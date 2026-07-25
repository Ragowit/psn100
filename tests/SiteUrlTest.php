<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/SiteUrl.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerUrlBuilder.php';

final class SiteUrlTest extends TestCase
{
    public function testAbsoluteBuildsCanonicalSiteUrls(): void
    {
        $this->assertSame('https://psn100.net/game/1-example', SiteUrl::absolute('/game/1-example'));
        $this->assertSame('https://psn100.net/img/title/icon.png', SiteUrl::absolute('img/title/icon.png'));
        $this->assertSame('https://psn100.net/', SiteUrl::absolute('/'));
    }

    public function testAbsoluteComposesWithPlayerUrlBuilder(): void
    {
        $this->assertSame(
            'https://psn100.net/player/Queue%20%3CUser%3E',
            SiteUrl::absolute(PlayerUrlBuilder::playerPath('Queue <User>'))
        );
    }
}
