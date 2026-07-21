<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameTrophySort.php';
require_once __DIR__ . '/../wwwroot/classes/HttpMethod.php';

final class Php85IdiomEnumsTest extends TestCase
{
    public function testGameTrophySortFromMixedNormalizesValues(): void
    {
        $this->assertSame(GameTrophySort::Default, GameTrophySort::fromMixed(null));
        $this->assertSame(GameTrophySort::Default, GameTrophySort::fromMixed('unknown'));
        $this->assertSame(GameTrophySort::Date, GameTrophySort::fromMixed(' Date '));
        $this->assertSame(GameTrophySort::Rarity, GameTrophySort::fromMixed('RARITY'));
    }

    public function testHttpMethodFromServerDefaultsToGet(): void
    {
        $this->assertSame(HttpMethod::Get, HttpMethod::fromServer([]));
        $this->assertSame(HttpMethod::Post, HttpMethod::fromServer(['REQUEST_METHOD' => 'post']));
        $this->assertTrue(HttpMethod::fromMixed('POST')->isPost());
        $this->assertTrue(HttpMethod::fromMixed('GET')->isGet());
    }
}
