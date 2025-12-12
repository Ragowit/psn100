<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/TestRunReporter.php';
require_once __DIR__ . '/TestSuiteResult.php';
require_once __DIR__ . '/TestResult.php';

final class TestRunReporterTest extends TestCase
{
    public function testReportOutputsSummaryForSuccessfulRun(): void
    {
        $output = [];
        $reporter = new TestRunReporter(static function (string $line) use (&$output): void {
            $output[] = $line;
        });

        $result = new TestSuiteResult([
            new TestResult('ExampleTest', 'testExample', TestStatus::PASSED),
        ]);

        $exitCode = $reporter->report($result);

        $this->assertSame(0, $exitCode);
        $this->assertSame([
            '[PASS] ExampleTest::testExample',
            '',
            'All 1 tests passed.',
        ], $output);
    }

    public function testReportOutputsSummaryForFailedRun(): void
    {
        $output = [];
        $reporter = new TestRunReporter(static function (string $line) use (&$output): void {
            $output[] = $line;
        });

        $result = new TestSuiteResult([
            new TestResult('ExampleTest', 'testSuccess', TestStatus::PASSED),
            new TestResult('ExampleTest', 'testFailure', TestStatus::FAILED, 'Something went wrong'),
        ]);

        $exitCode = $reporter->report($result);

        $this->assertSame(1, $exitCode);
        $this->assertSame([
            '[PASS] ExampleTest::testSuccess',
            '[FAIL] ExampleTest::testFailure - Something went wrong',
            '',
            'Test run completed with 1 failure(s) and 0 error(s) out of 2 tests.',
        ], $output);
    }

    public function testReportReturnsErrorCodeWhenNoTestsExecuted(): void
    {
        $output = [];
        $reporter = new TestRunReporter(static function (string $line) use (&$output): void {
            $output[] = $line;
        });

        $result = new TestSuiteResult([]);

        $exitCode = $reporter->report($result);

        $this->assertSame(1, $exitCode);
        $this->assertSame(['No tests were executed.'], $output);
    }
}
