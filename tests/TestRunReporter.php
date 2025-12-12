<?php

declare(strict_types=1);

require_once __DIR__ . '/TestSuiteResult.php';

final readonly class TestRunReporter
{
    private \Closure $outputWriter;

    /**
     * @param callable(string): void|null $outputWriter
     */
    public function __construct(?callable $outputWriter = null)
    {
        $this->outputWriter = $outputWriter === null
            ? static function (string $line): void {
                echo $line . PHP_EOL;
            }
            : \Closure::fromCallable($outputWriter);
    }

    public function report(TestSuiteResult $result): int
    {
        foreach ($result->getResults() as $testResult) {
            $status = match ($testResult->getStatus()) {
                TestStatus::PASSED => 'PASS',
                TestStatus::FAILED => 'FAIL',
                TestStatus::ERROR => 'ERROR',
            };
            $className = $testResult->getClassName();
            $methodName = $testResult->getMethodName();
            $message = $testResult->getMessage() ?? '';

            if ($testResult->isPassed()) {
                $this->write(sprintf('[%s] %s::%s', $status, $className, $methodName));

                continue;
            }

            $this->write(sprintf('[%s] %s::%s - %s', $status, $className, $methodName, $message));
        }

        if ($result->getTotalTests() === 0) {
            $this->write('No tests were executed.');

            return 1;
        }

        $this->write('');

        if ($result->isSuccessful()) {
            $this->write(sprintf('All %d tests passed.', $result->getTotalTests()));

            return 0;
        }

        $this->write(sprintf(
            'Test run completed with %d failure(s) and %d error(s) out of %d tests.',
            $result->getFailureCount(),
            $result->getErrorCount(),
            $result->getTotalTests()
        ));

        return 1;
    }

    private function write(string $line): void
    {
        ($this->outputWriter)($line);
    }
}
