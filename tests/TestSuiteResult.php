<?php

declare(strict_types=1);

require_once __DIR__ . '/TestResult.php';

final readonly class TestSuiteResult
{
    /**
     * @var list<TestResult>
     */
    private array $results;

    /**
     * @param list<TestResult> $results
     */
    public function __construct(array $results)
    {
        $this->results = array_values($results);
    }

    /**
     * @return list<TestResult>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getTotalTests(): int
    {
        return count($this->results);
    }

    public function getFailureCount(): int
    {
        return $this->countByStatus(TestStatus::FAILED);
    }

    public function getErrorCount(): int
    {
        return $this->countByStatus(TestStatus::ERROR);
    }

    public function isSuccessful(): bool
    {
        return $this->getFailureCount() === 0 && $this->getErrorCount() === 0;
    }

    private function countByStatus(TestStatus $status): int
    {
        $count = 0;

        foreach ($this->results as $result) {
            if ($result->getStatus() === $status) {
                $count++;
            }
        }

        return $count;
    }
}
