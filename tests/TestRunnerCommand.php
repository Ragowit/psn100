<?php

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';

final class TestRunnerCommand
{
    private TestRunner $runner;

    public function __construct(TestRunner $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param callable(string): void|null $outputWriter
     */
    public static function fromDirectory(string $directory, ?callable $outputWriter = null): self
    {
        $runner = TestRunner::fromDirectory($directory, $outputWriter);

        return new self($runner);
    }

    public function execute(): int
    {
        return $this->runner->run();
    }
}
