<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/TestResult.php';
require_once __DIR__ . '/TestSuiteResult.php';

final class TestSuite
{
    private string $directory;

    /**
     * @var list<class-string<TestCase>>
     */
    private array $testClasses;

    /**
     * @param list<class-string<TestCase>> $testClasses
     */
    private function __construct(string $directory, array $testClasses)
    {
        $this->directory = $directory;
        $this->testClasses = $testClasses;
    }

    public static function fromDirectory(string $directory): self
    {
        $normalizedDirectory = rtrim($directory, DIRECTORY_SEPARATOR);
        if ($normalizedDirectory === '') {
            $normalizedDirectory = '.';
        }

        $resolvedDirectory = realpath($normalizedDirectory);

        if ($resolvedDirectory === false || !is_dir($resolvedDirectory)) {
            throw new InvalidArgumentException(sprintf('The directory "%s" does not exist.', $directory));
        }

        $testFiles = glob($resolvedDirectory . '/*Test.php');

        if ($testFiles === false) {
            $testFiles = [];
        }

        $beforeClasses = get_declared_classes();

        foreach ($testFiles as $testFile) {
            require_once $testFile;
        }

        $afterClasses = get_declared_classes();
        $newClasses = array_diff($afterClasses, $beforeClasses);

        $testClasses = array_values(array_filter(
            $newClasses,
            static fn (string $className): bool => is_subclass_of($className, TestCase::class)
        ));

        sort($testClasses);

        return new self($resolvedDirectory, $testClasses);
    }

    public function run(): TestSuiteResult
    {
        $results = [];

        foreach ($this->testClasses as $testClass) {
            $testCase = new $testClass();

            foreach ($testCase->runTests() as $result) {
                $results[] = new TestResult(
                    $testClass,
                    $result['method'],
                    $result['status'],
                    $result['message'] ?? null
                );
            }
        }

        return new TestSuiteResult($results);
    }

    /**
     * @return list<class-string<TestCase>>
     */
    public function getTestClasses(): array
    {
        return $this->testClasses;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }
}
