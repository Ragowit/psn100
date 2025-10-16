<?php

declare(strict_types=1);

final class AdminRequest
{
    private string $method;

    /**
     * @var array<string, mixed>
     */
    private array $postData;

    /**
     * @param array<string, mixed> $postData
     */
    public function __construct(string $method, array $postData)
    {
        $normalizedMethod = strtoupper(trim($method));
        $this->method = $normalizedMethod === '' ? 'GET' : $normalizedMethod;
        $this->postData = $postData;
    }

    /**
     * @param array<string, mixed> $serverData
     * @param array<string, mixed> $postData
     */
    public static function fromGlobals(array $serverData, array $postData): self
    {
        $method = $serverData['REQUEST_METHOD'] ?? 'GET';

        if (!is_string($method)) {
            $method = 'GET';
        }

        return new self($method, $postData);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function getPostValue(string $key): mixed
    {
        return $this->postData[$key] ?? null;
    }

    public function getPostString(string $key): string
    {
        $value = $this->getPostValue($key);

        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    public function getPostInt(string $key): ?int
    {
        $value = $this->getPostValue($key);

        if (!is_scalar($value)) {
            return null;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? null : (int) $filtered;
    }

    public function getPostNonNegativeInt(string $key): ?int
    {
        $number = $this->getPostInt($key);

        if ($number === null || $number < 0) {
            return null;
        }

        return $number;
    }

    public function getPostPositiveInt(string $key): ?int
    {
        $number = $this->getPostInt($key);

        if ($number === null || $number <= 0) {
            return null;
        }

        return $number;
    }

    /**
     * @param array<int, int> $allowedValues
     */
    public function getPostIntInSet(string $key, array $allowedValues): ?int
    {
        $number = $this->getPostInt($key);

        if ($number === null) {
            return null;
        }

        return in_array($number, $allowedValues, true) ? $number : null;
    }
}
