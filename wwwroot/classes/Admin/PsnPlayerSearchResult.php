<?php

declare(strict_types=1);

final class PsnPlayerSearchResult
{
    private string $onlineId;

    private string $accountId;

    private string $npId;

    private string $country;

    public function __construct(string $onlineId, string $accountId, string $npId, string $country)
    {
        $this->onlineId = $onlineId;
        $this->accountId = $accountId;
        $this->npId = $npId;
        $this->country = $country;
    }

    public static function fromUserSearchResult(object $userSearchResult): self
    {
        $onlineId = method_exists($userSearchResult, 'onlineId') ? (string) $userSearchResult->onlineId() : '';
        $accountId = method_exists($userSearchResult, 'accountId') ? (string) $userSearchResult->accountId() : '';

        $encodedNpId = null;

        if (method_exists($userSearchResult, 'npId')) {
            $npIdValue = $userSearchResult->npId();
            $encodedNpId = is_string($npIdValue) ? $npIdValue : null;
        }

        [$npId, $country] = self::normalizeNpId($encodedNpId);

        return new self($onlineId, $accountId, $npId, $country);
    }

    public static function fromProfileData(string $onlineId, string $accountId, ?object $profile): self
    {
        $encodedNpId = null;

        if (is_object($profile)) {
            $npIdValue = $profile->npId ?? null;
            $encodedNpId = is_string($npIdValue) ? $npIdValue : null;
        }

        [$npId, $country] = self::normalizeNpId($encodedNpId);

        return new self($onlineId, $accountId, $npId, $country);
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getNpId(): string
    {
        return $this->npId;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function normalizeNpId(?string $encodedNpId): array
    {
        if ($encodedNpId === null) {
            return ['', ''];
        }

        $decoded = self::decodeNpId($encodedNpId);

        if ($decoded === null || $decoded === '') {
            return ['', ''];
        }

        return [$decoded, self::extractCountryFromNpId($decoded)];
    }

    private static function decodeNpId(string $encodedNpId): ?string
    {
        $normalized = trim($encodedNpId);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['-', '_'], ['+', '/'], $normalized);

        $remainder = strlen($normalized) % 4;

        if ($remainder !== 0) {
            $normalized .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }

    private static function extractCountryFromNpId(string $npId): string
    {
        $atPosition = strrpos($npId, '@');

        if ($atPosition === false) {
            return '';
        }

        $domain = substr($npId, $atPosition + 1);

        if ($domain === false) {
            return '';
        }

        $domain = trim($domain);

        if ($domain === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('.', $domain), static fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return '';
        }

        $country = strtoupper((string) array_pop($parts));

        return $country;
    }
}
