<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/CronJobRunner.php';

final class CronJobRunnerTest extends TestCase
{
    private string|false $originalMemoryLimit;

    private string|false $originalMaxExecutionTime;

    private string|false $originalMysqlConnectTimeout;

    protected function setUp(): void
    {
        $this->originalMemoryLimit = ini_get('memory_limit');
        $this->originalMaxExecutionTime = ini_get('max_execution_time');
        $this->originalMysqlConnectTimeout = ini_get('mysql.connect_timeout');
    }

    protected function tearDown(): void
    {
        if ($this->originalMemoryLimit !== false) {
            ini_set('memory_limit', (string) $this->originalMemoryLimit);
        }

        if ($this->originalMaxExecutionTime !== false) {
            ini_set('max_execution_time', (string) $this->originalMaxExecutionTime);
        }

        if ($this->originalMysqlConnectTimeout !== false) {
            ini_set('mysql.connect_timeout', (string) $this->originalMysqlConnectTimeout);
        }
    }

    public function testConfigureEnvironmentRaisesLowMemoryLimit(): void
    {
        ini_set('memory_limit', '128M');
        ini_set('max_execution_time', '30');
        ini_set('mysql.connect_timeout', '15');

        $runner = CronJobRunner::create();
        $runner->configureEnvironment();

        $this->assertSame('512M', ini_get('memory_limit'));

        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime !== false) {
            $this->assertSame('0', $maxExecutionTime);
        }

        $mysqlConnectTimeout = ini_get('mysql.connect_timeout');
        if ($mysqlConnectTimeout !== false) {
            $this->assertSame('0', $mysqlConnectTimeout);
        }
    }

    public function testConfigureEnvironmentKeepsHigherMemoryLimit(): void
    {
        ini_set('memory_limit', '1024M');

        $runner = CronJobRunner::create();
        $runner->configureEnvironment();

        $this->assertSame('1024M', ini_get('memory_limit'));
    }
}
