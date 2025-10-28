<?php

declare(strict_types=1);

final class GameRescanResult
{
    private string $message;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $differences;

    /**
     * @param array<int, array<string, mixed>> $differences
     */
    public function __construct(string $message, array $differences)
    {
        $this->message = $message;
        $this->differences = array_values($differences);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDifferences(): array
    {
        return $this->differences;
    }
}
