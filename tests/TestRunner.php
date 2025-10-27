<?php

declare(strict_types=1);

require_once __DIR__ . '/TestSuiteInterface.php';
require_once __DIR__ . '/TestSuite.php';

final class TestRunner
{
    private TestSuiteInterface $suite;

    /**
     * @var callable(string): void
     */
    private $outputWriter;

    /**
     * @param callable(string): void|null $outputWriter
     */
    public function __construct(TestSuiteInterface $suite, ?callable $outputWriter = null)
    {
        $this->suite = $suite;
        $this->outputWriter = $outputWriter ?? static function (string $line): void {
            echo $line . PHP_EOL;
        };
    }

    /**
     * @param callable(string): void|null $outputWriter
     */
    public static function fromDirectory(string $directory, ?callable $outputWriter = null): self
    {
        $suite = TestSuite::fromDirectory($directory);

        return new self($suite, $outputWriter);
    }

    public function run(): int
    {
        $result = $this->suite->run();
        $statusMap = [
            'passed' => 'PASS',
            'failed' => 'FAIL',
            'error' => 'ERROR',
        ];

        foreach ($result->getResults() as $testResult) {
            $status = $statusMap[$testResult->getStatus()] ?? strtoupper($testResult->getStatus());
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
