<?php

declare(strict_types=1);

final class IpAddressResolver
{
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
}
