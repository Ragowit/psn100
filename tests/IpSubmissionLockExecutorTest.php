<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/IpSubmissionLockExecutor.php';

final class IpSubmissionLockExecutorTest extends TestCase
{
    public function testBuildLockNameFitsMysqlLimit(): void
    {
        $executor = new IpSubmissionLockExecutor(new PDO('sqlite::memory:'));
        $method = new ReflectionMethod($executor, 'buildLockName');
        $method->setAccessible(true);

        $ipv4LockName = $method->invoke($executor, '192.0.2.1');
        $ipv6LockName = $method->invoke($executor, '2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        $this->assertTrue(strlen($ipv4LockName) <= 64);
        $this->assertTrue(strlen($ipv6LockName) <= 64);
        $this->assertTrue(str_starts_with($ipv4LockName, 'psn100:ip:'));
        $this->assertTrue(str_starts_with($ipv6LockName, 'psn100:ip:'));
    }
}
