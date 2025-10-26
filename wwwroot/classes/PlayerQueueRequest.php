<?php

declare(strict_types=1);

class PlayerQueueRequest
{
    private string $playerName;

    private string $ipAddress;

    private function __construct(string $playerName, string $ipAddress)
    {
        $this->playerName = $playerName;
        $this->ipAddress = $ipAddress;
    }

    public static function fromArrays(array $requestData, array $serverData): self
    {
        $playerName = self::sanitizeValue($requestData['q'] ?? '');
        $ipAddress = self::sanitizeValue($serverData['REMOTE_ADDR'] ?? '');

        return new self($playerName, $ipAddress);
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function isPlayerNameEmpty(): bool
    {
        return $this->playerName === '';
    }

    private static function sanitizeValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $value = (string) $value;
            } else {
                return '';
            }
        }

        if (!is_scalar($value) && $value !== null) {
            return '';
        }

        return trim((string) ($value ?? ''));
    }
}
