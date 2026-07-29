<?php

declare(strict_types=1);

/**
 * Extracts a path from a request URI that may contain non-ASCII bytes.
 *
 * Apache often exposes decoded UTF-8 in SCRIPT_URL / REQUEST_URI. The WHATWG
 * URL parser accepts those bytes and percent-encodes the resulting path the
 * same way browsers do, so Unicode game URLs route correctly.
 */
final readonly class RequestUriPath
{
    private const string BASE_URI = 'http://localhost/';

    #[\NoDiscard]
    public static function fromUri(string $requestUri): string
    {
        return Uri\WhatWg\Url::parse(
            $requestUri,
            new Uri\WhatWg\Url(self::BASE_URI),
        )?->getPath() ?? '';
    }
}
