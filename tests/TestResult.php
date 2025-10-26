<?php

declare(strict_types=1);

final class TestResult
{
    private string $className;

    private string $methodName;

    private string $status;

    private ?string $message;

    public function __construct(string $className, string $methodName, string $status, ?string $message = null)
    {
        $this->className = $className;
        $this->methodName = $methodName;
        $this->status = $status;
        $this->message = $message;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function isPassed(): bool
    {
        return $this->status === 'passed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isError(): bool
    {
        return $this->status === 'error';
    }
}
