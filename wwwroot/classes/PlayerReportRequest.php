<?php

declare(strict_types=1);

final readonly class PlayerReportRequest
{
    private function __construct(
        private string $explanation,
        private bool $explanationSubmitted,
        private string $ipAddress,
    ) {}

    /**
     * @param array<string, mixed> $postParameters
     * @param array<string, mixed> $serverParameters
     */
    public static function fromArrays(array $postParameters, array $serverParameters): self
    {
        $explanationSubmitted = array_key_exists('explanation', $postParameters);
        $explanation = self::sanitizeExplanation($postParameters['explanation'] ?? null);
        $ipAddress = self::resolveIpAddress($serverParameters);

        return new self($explanation, $explanationSubmitted, $ipAddress);
    }

    public function getExplanation(): string
    {
        return $this->explanation;
    }

    public function wasExplanationSubmitted(): bool
    {
        return $this->explanationSubmitted;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    private static function sanitizeExplanation(mixed $explanation): string
    {
        if (!is_scalar($explanation)) {
            return '';
        }

        return trim((string) $explanation);
    }

    /**
     * @param array<string, mixed> $serverParameters
     */
    private static function resolveIpAddress(array $serverParameters): string
    {
        $ipAddress = (string) ($serverParameters['REMOTE_ADDR'] ?? '');
        if ($ipAddress === '') {
            return '';
        }

        $validatedAddress = filter_var($ipAddress, FILTER_VALIDATE_IP);

        return is_string($validatedAddress) ? $validatedAddress : '';
    }
}
