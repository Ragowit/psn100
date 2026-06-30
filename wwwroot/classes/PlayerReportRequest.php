<?php

declare(strict_types=1);

require_once __DIR__ . '/IpAddressResolver.php';

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
        $ipAddress = IpAddressResolver::resolve($serverParameters['REMOTE_ADDR'] ?? '');

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

}
