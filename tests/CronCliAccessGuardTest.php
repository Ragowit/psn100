<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/CronCliAccessGuard.php';

final class CronCliAccessGuardTest extends TestCase
{
    public function testIsCronScriptRequestDetectsCronPaths(): void
    {
        $this->assertTrue(CronCliAccessGuard::isCronScriptRequest([
            'SCRIPT_NAME' => '/cron/hourly.php',
        ]));
        $this->assertTrue(CronCliAccessGuard::isCronScriptRequest([
            'PHP_SELF' => '/cron/30th_minute.php',
        ]));
    }

    public function testIsCronScriptRequestIgnoresPublicPages(): void
    {
        $this->assertFalse(CronCliAccessGuard::isCronScriptRequest([
            'SCRIPT_NAME' => '/index.php',
        ]));
        $this->assertFalse(CronCliAccessGuard::isCronScriptRequest([
            'SCRIPT_NAME' => '/admin/login.php',
        ]));
        $this->assertFalse(CronCliAccessGuard::isCronScriptRequest([]));
    }

    public function testDenyWebCronScriptAccessDoesNothingInCli(): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            $this->markTestSkipped('This assertion only applies under the CLI SAPI.');
        }

        CronCliAccessGuard::denyWebCronScriptAccess([
            'SCRIPT_NAME' => '/cron/hourly.php',
        ]);

        $this->assertTrue(true);
    }
}
