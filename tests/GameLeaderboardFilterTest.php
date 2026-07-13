<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GamePlayerFilter.php';
require_once __DIR__ . '/../wwwroot/classes/GameLeaderboardFilter.php';

final class GameLeaderboardFilterTest extends TestCase
{
    public function testWithPageNumberReturnsNewFilterInstance(): void
    {
        $filter = GameLeaderboardFilter::fromArray(['page' => '2', 'country' => 'us']);

        $updated = $filter->withPageNumber(4);

        $this->assertSame(2, $filter->getPage());
        $this->assertSame(4, $updated->getPage());
        $this->assertSame('us', $updated->getCountry());
        $this->assertSame(150, $updated->getOffset(50));
    }
}
