<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/StaticAsset.php';

final class StaticAssetTest extends TestCase
{
    public function testUrlAppendsFileModificationTimeForExistingAsset(): void
    {
        $url = StaticAsset::url('/js/localized-date-formatter.js');

        $this->assertStringContainsString('/js/localized-date-formatter.js?v=', $url);
        $this->assertTrue((bool) preg_match('/\?v=\d+$/', $url));
    }

    public function testUrlReturnsPathUnchangedWhenAssetIsMissing(): void
    {
        $this->assertSame('/js/does-not-exist.js', StaticAsset::url('/js/does-not-exist.js'));
    }
}
