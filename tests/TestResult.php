<?php

declare(strict_types=1);

require_once __DIR__ . '/TestStatus.php';

final readonly class TestResult
{
    public function __construct(
        private string $className,
        private string $methodName,
        private TestStatus $status,
        private ?string $message = null,
    ) {
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getStatus(): TestStatus
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function isPassed(): bool
    {
        return $this->status === TestStatus::PASSED;
    }

    public function isFailed(): bool
    {
        return $this->status === TestStatus::FAILED;
    }

    public function isError(): bool
    {
        return $this->status === TestStatus::ERROR;
    }
}
