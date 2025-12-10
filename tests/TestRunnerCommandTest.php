<?php

declare(strict_types=1);

require_once __DIR__ . '/TestRunnerCommand.php';
require_once __DIR__ . '/TestSuiteInterface.php';
require_once __DIR__ . '/TestSuiteResult.php';
require_once __DIR__ . '/TestResult.php';

final class TestRunnerCommandSuiteStub implements TestSuiteInterface
{
    private TestSuiteResult $result;

    public function __construct(TestSuiteResult $result)
    {
        $this->result = $result;
    }

    #[\Override]
    public function run(): TestSuiteResult
    {
        return $this->result;
    }
}

final class TestRunnerCommandTest extends TestCase
{
    public function testExecuteDelegatesToRunner(): void
    {
        $suite = new TestRunnerCommandSuiteStub(new TestSuiteResult([]));
        $runner = new TestRunner($suite, static function (string $line): void {
            // Intentionally left blank for testing purposes.
        });

        $command = new TestRunnerCommand($runner);

        $this->assertSame(1, $command->execute());
    }

    public function testFromDirectoryCreatesCommandUsingTestRunner(): void
    {
        $command = TestRunnerCommand::fromDirectory(__DIR__, static function (string $line): void {
            // Suppress output during tests.
        });

        $runnerProperty = new ReflectionProperty(TestRunnerCommand::class, 'runner');
        $runnerProperty->setAccessible(true);

        $runner = $runnerProperty->getValue($command);

        $this->assertTrue($runner instanceof TestRunner);
    }
}
