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

    public function testCronEntryScriptsRequireBootstrapGuard(): void
    {
        $entryScripts = glob(__DIR__ . '/../wwwroot/cron/*.php') ?: [];

        foreach ($entryScripts as $scriptPath) {
            if (str_ends_with($scriptPath, '/bootstrap.php')) {
                continue;
            }

            $source = file_get_contents($scriptPath);
            $this->assertTrue(is_string($source));

            $this->assertStringContainsString(
                "require_once __DIR__ . '/bootstrap.php'",
                $source,
                basename($scriptPath) . ' must load cron/bootstrap.php before other bootstrap logic.'
            );
        }
    }
}
