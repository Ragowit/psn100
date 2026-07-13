<?php

declare(strict_types=1);

final class IpAddressResolver
{
    public const string UNKNOWN_CLIENT_IDENTIFIER = '__unknown__';

    /**
     * @param array<string, mixed> $serverParameters
     */
    public static function resolveFromServer(array $serverParameters): string
    {
        return self::resolveFromServerWithTrustedProxies(
            $serverParameters,
            self::readTrustedProxyIpsFromEnvironment()
        );
    }

    /**
     * @param array<string, mixed> $serverParameters
     * @param list<string> $trustedProxyIps
     */
    public static function resolveFromServerWithTrustedProxies(
        array $serverParameters,
        array $trustedProxyIps,
    ): string {
        $remoteAddress = self::resolve($serverParameters['REMOTE_ADDR'] ?? '');

        if ($remoteAddress === '' || !in_array($remoteAddress, $trustedProxyIps, true)) {
            return $remoteAddress;
        }

        $forwardedClientIp = self::resolveForwardedClientIp($serverParameters['HTTP_X_FORWARDED_FOR'] ?? null);
        if ($forwardedClientIp !== '') {
            return $forwardedClientIp;
        }

        return $remoteAddress;
    }

    public static function resolve(mixed $remoteAddr): string
    {
        if (is_array($remoteAddr)) {
            $remoteAddr = reset($remoteAddr);
        }

        if (is_object($remoteAddr) && method_exists($remoteAddr, '__toString')) {
            $remoteAddr = (string) $remoteAddr;
        } elseif (!is_scalar($remoteAddr) && $remoteAddr !== null) {
            return '';
        }

        $ipAddress = trim((string) ($remoteAddr ?? ''));
        if ($ipAddress === '') {
            return '';
        }

        $validatedAddress = filter_var($ipAddress, FILTER_VALIDATE_IP);

        return is_string($validatedAddress) ? $validatedAddress : '';
    }

    public static function normalizeForAbuseControls(string $ipAddress): string
    {
        return $ipAddress !== '' ? $ipAddress : self::UNKNOWN_CLIENT_IDENTIFIER;
    }

    /**
     * @return list<string>
     */
    private static function readTrustedProxyIpsFromEnvironment(): array
    {
        $value = getenv('TRUSTED_PROXY_IPS');
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $proxyIps = [];
        foreach (explode(',', $value) as $proxyIp) {
            $proxyIp = trim($proxyIp);
            if ($proxyIp !== '') {
                $proxyIps[] = $proxyIp;
            }
        }

        return $proxyIps;
    }

    private static function resolveForwardedClientIp(mixed $forwardedFor): string
    {
        if (!is_string($forwardedFor) || trim($forwardedFor) === '') {
            return '';
        }

        foreach (explode(',', $forwardedFor) as $candidate) {
            $validatedAddress = self::resolve(trim($candidate));
            if ($validatedAddress !== '') {
                return $validatedAddress;
            }
        }

        return '';
    }
}
