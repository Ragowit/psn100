<?php

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/TestSuiteResult.php';
require_once __DIR__ . '/TestResult.php';

final class TestSuiteStub implements TestSuiteInterface
{
    private TestSuiteResult $result;

    public function __construct(TestSuiteResult $result)
    {
        $this->result = $result;
    }

    public function run(): TestSuiteResult
    {
        return $this->result;
    }
}

final class TestRunnerTest extends TestCase
{
    public function testRunOutputsSummaryForSuccessfulRun(): void
    {
        $suite = new TestSuiteStub(new TestSuiteResult([
            new TestResult('ExampleTest', 'testExample', 'passed'),
        ]));

        $output = [];
        $runner = new TestRunner($suite, static function (string $line) use (&$output): void {
            $output[] = $line;
        });

        $exitCode = $runner->run();

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            '[PASS] ExampleTest::testExample',
            '',
            'All 1 tests passed.',
        ], $output);
    }

    public function testRunOutputsSummaryForFailedRun(): void
    {
        $suite = new TestSuiteStub(new TestSuiteResult([
            new TestResult('ExampleTest', 'testSuccess', 'passed'),
            new TestResult('ExampleTest', 'testFailure', 'failed', 'Something went wrong'),
        ]));

        $output = [];
        $runner = new TestRunner($suite, static function (string $line) use (&$output): void {
            $output[] = $line;
        });

        $exitCode = $runner->run();

        $this->assertSame(1, $exitCode);
        $this->assertSame([
            '[PASS] ExampleTest::testSuccess',
            '[FAIL] ExampleTest::testFailure - Something went wrong',
            '',
            'Test run completed with 1 failure(s) and 0 error(s) out of 2 tests.',
        ], $output);
    }

    public function testRunReturnsErrorCodeWhenNoTestsExecuted(): void
    {
        $suite = new TestSuiteStub(new TestSuiteResult([]));

        $output = [];
        $runner = new TestRunner($suite, static function (string $line) use (&$output): void {
            $output[] = $line;
        });

        $exitCode = $runner->run();

        $this->assertSame(1, $exitCode);
        $this->assertSame(['No tests were executed.'], $output);
    }
}
