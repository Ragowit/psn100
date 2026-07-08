<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerUrlBuilder.php';

final class PlayerUrlBuilderTest extends TestCase
{
    public function testPlayerPathEncodesOnlineId(): void
    {
        $this->assertSame('/player/Queue%20%3CUser%3E', PlayerUrlBuilder::playerPath('Queue <User>'));
    }

    public function testPlayerReportPathAppendsReportSegment(): void
    {
        $this->assertSame('/player/Bad%20User/report', PlayerUrlBuilder::playerReportPath('Bad User'));
    }

    public function testGamePathOmitsPlayerWhenNotProvided(): void
    {
        $this->assertSame('/game/42-example-game', PlayerUrlBuilder::gamePath('42-example-game'));
    }

    public function testGamePathEncodesPlayerSegment(): void
    {
        $this->assertSame(
            '/game/42-example-game/Player%20One',
            PlayerUrlBuilder::gamePath('42-example-game', 'Player One')
        );
    }
}
