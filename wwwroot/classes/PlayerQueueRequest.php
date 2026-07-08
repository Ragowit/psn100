<?php

declare(strict_types=1);

require_once __DIR__ . '/IpAddressResolver.php';

readonly class PlayerQueueRequest
{
    private function __construct(
        private string $playerName,
        private string $ipAddress,
        private string $pollToken,
    ) {}

    public static function fromArrays(array $requestData, array $serverData): self
    {
        $playerName = self::sanitizeValue($requestData['q'] ?? '');
        $ipAddress = IpAddressResolver::resolveFromServer($serverData);
        $pollToken = self::sanitizeValue($requestData['poll_token'] ?? $requestData['pollToken'] ?? '');

        return new self($playerName, $ipAddress, $pollToken);
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getPollToken(): string
    {
        return $this->pollToken;
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
