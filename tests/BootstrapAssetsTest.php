<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/BootstrapAssets.php';

final class BootstrapAssetsTest extends TestCase
{
    public function testStylesheetUrlPointsAtSelfHostedBootstrapCss(): void
    {
        $url = BootstrapAssets::stylesheetUrl();

        $this->assertStringContainsString('/lib/bootstrap/5.3.8/css/bootstrap.min.css?v=', $url);
        $this->assertTrue((bool) preg_match('/\?v=\d+$/', $url));
    }

    public function testScriptUrlPointsAtSelfHostedBootstrapJs(): void
    {
        $url = BootstrapAssets::scriptUrl();

        $this->assertStringContainsString('/lib/bootstrap/5.3.8/js/bootstrap.min.js?v=', $url);
        $this->assertTrue((bool) preg_match('/\?v=\d+$/', $url));
    }

    public function testPopperScriptUrlPointsAtSelfHostedPopperJs(): void
    {
        $url = BootstrapAssets::popperScriptUrl();

        $this->assertStringContainsString('/lib/popper/2.11.8/popper.min.js?v=', $url);
        $this->assertTrue((bool) preg_match('/\?v=\d+$/', $url));
    }
}
