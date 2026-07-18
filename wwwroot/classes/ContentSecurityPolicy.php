<?php

declare(strict_types=1);

final class ContentSecurityPolicy
{
    public const string HEADER_NAME = 'Content-Security-Policy';

    public static function value(): string
    {
        return "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: https:; "
            . "font-src 'self'; "
            . "connect-src 'self'; "
            . "frame-ancestors 'self'; "
            . "base-uri 'self'; "
            . "form-action 'self'";
    }
}
