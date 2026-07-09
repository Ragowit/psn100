<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerCredentialMasker.php';

final class WorkerCredentialMaskerTest extends TestCase
{
    public function testMaskReturnsNotConfiguredForEmptySecret(): void
    {
        $this->assertSame('Not configured', WorkerCredentialMasker::mask(''));
    }

    public function testMaskHidesShortSecretsCompletely(): void
    {
        $this->assertSame('••••••••', WorkerCredentialMasker::mask('abc'));
    }

    public function testMaskShowsOnlyLastFourCharacters(): void
    {
        $this->assertSame('••••••••mnop', WorkerCredentialMasker::mask('abcdefghijklmnop'));
    }

    public function testIsConfiguredDetectsEmptySecrets(): void
    {
        $this->assertFalse(WorkerCredentialMasker::isConfigured(''));
        $this->assertTrue(WorkerCredentialMasker::isConfigured('token'));
    }
}
