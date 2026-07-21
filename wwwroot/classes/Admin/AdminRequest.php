<?php

declare(strict_types=1);

require_once __DIR__ . '/HttpMethod.php';

final readonly class AdminRequest
{
    private HttpMethod $method;

    /**
     * @param array<string, mixed> $postData
     */
    public function __construct(
        string|HttpMethod $method,
        private array $postData,
    ) {
        $this->method = $method instanceof HttpMethod ? $method : HttpMethod::fromMixed($method);
    }

    /**
     * @param array<string, mixed> $serverData
     * @param array<string, mixed> $postData
     */
    #[\NoDiscard]
    public static function fromGlobals(array $serverData, array $postData): self
    {
        return new self(HttpMethod::fromServer($serverData), $postData);
    }

    public function getMethod(): HttpMethod
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method->isPost();
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
