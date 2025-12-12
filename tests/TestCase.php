<?php

declare(strict_types=1);

require_once __DIR__ . '/TestResult.php';
require_once __DIR__ . '/TestStatus.php';

abstract class TestCase
{
    /**
     * @return list<TestResult>
     */
    public final function runTests(): array
    {
        $results = [];
        $className = get_class($this);

        foreach ($this->getTestMethods() as $method) {
            $this->setUp();

            try {
                $this->$method();
                $results[] = new TestResult($className, $method, TestStatus::PASSED);
            } catch (AssertionError $assertionError) {
                $results[] = new TestResult(
                    $className,
                    $method,
                    TestStatus::FAILED,
                    $assertionError->getMessage()
                );
            } catch (Throwable $throwable) {
                $results[] = new TestResult(
                    $className,
                    $method,
                    TestStatus::ERROR,
                    $throwable->getMessage()
                );
            } finally {
                $this->tearDown();
            }
        }

        return $results;
    }

    /**
     * @return list<string>
     */
    private function getTestMethods(): array
    {
        $methods = get_class_methods($this);

        return array_values(array_filter(
            $methods,
            static fn (string $method): bool => str_starts_with($method, 'test')
        ));
    }

    protected function setUp(): void
    {
        // Intended to be overridden by extending classes when needed.
    }

    protected function tearDown(): void
    {
        // Intended to be overridden by extending classes when needed.
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $this->fail(
                $message !== ''
                    ? $message
                    : sprintf('Failed asserting that %s matches expected %s.', var_export($actual, true), var_export($expected, true))
            );
        }
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        if ($condition !== true) {
            $this->fail($message !== '' ? $message : 'Failed asserting that condition is true.');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        if ($condition !== false) {
            $this->fail($message !== '' ? $message : 'Failed asserting that condition is false.');
        }
    }

    protected function assertCount(int $expectedCount, Countable|array $value, string $message = ''): void
    {
        if (count($value) !== $expectedCount) {
            $this->fail(
                $message !== ''
                    ? $message
                    : sprintf('Failed asserting that actual count %d matches expected count %d.', count($value), $expectedCount)
            );
        }
    }

    protected function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $this->fail(
                $message !== ''
                    ? $message
                    : sprintf('Failed asserting that "%s" contains "%s".', $haystack, $needle)
            );
        }
    }

    protected function fail(string $message): never
    {
        throw new AssertionError($message);
    }
}
