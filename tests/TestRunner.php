<?php

declare(strict_types=1);

require_once __DIR__ . '/TestSuiteInterface.php';
require_once __DIR__ . '/TestSuite.php';
require_once __DIR__ . '/TestRunReporter.php';

final readonly class TestRunner
{
    private TestRunReporter $reporter;

    /**
     * @param callable(string): void|null $outputWriter
     */
    public function __construct(
        private TestSuiteInterface $suite,
        ?callable $outputWriter = null,
    ) {
        $this->reporter = new TestRunReporter($outputWriter);
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

        return $this->reporter->report($result);
    }
}
