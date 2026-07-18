<?php

declare(strict_types=1);

require_once __DIR__ . '/IpAddressResolver.php';
require_once __DIR__ . '/CsrfTokenManager.php';

final readonly class PlayerReportRequest
{
    private function __construct(
        private string $explanation,
        private bool $explanationSubmitted,
        private string $ipAddress,
        private string $csrfToken,
    ) {}

    /**
     * @param array<string, mixed> $postParameters
     * @param array<string, mixed> $serverParameters
     */
    #[\NoDiscard]
    public static function fromArrays(array $postParameters, array $serverParameters): self
    {
        $explanationSubmitted = array_key_exists('explanation', $postParameters);
        $explanation = self::sanitizeExplanation($postParameters['explanation'] ?? null);
        $ipAddress = IpAddressResolver::resolveFromServer($serverParameters);
        $csrfToken = self::sanitizeCsrfToken($postParameters['_csrf_token'] ?? null);

        return new self($explanation, $explanationSubmitted, $ipAddress, $csrfToken);
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

    public function getCsrfToken(): string
    {
        return $this->csrfToken;
    }

    public function hasValidCsrfToken(): bool
    {
        return CsrfTokenManager::validate('public', $this->csrfToken);
    }

    private static function sanitizeExplanation(mixed $explanation): string
    {
        if (!is_scalar($explanation)) {
            return '';
        }

        return trim((string) $explanation);
    }

    private static function sanitizeCsrfToken(mixed $csrfToken): string
    {
        if (!is_scalar($csrfToken)) {
            return '';
        }

        return trim((string) $csrfToken);
    }
}
