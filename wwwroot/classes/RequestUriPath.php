<?php

declare(strict_types=1);

/**
 * Extracts a path from a request URI that may contain non-ASCII bytes.
 *
 * Apache often exposes decoded UTF-8 in SCRIPT_URL / REQUEST_URI, but
 * {@see Uri\Rfc3986\Uri::parse()} only accepts ASCII or percent-encoded URIs.
 */
final class RequestUriPath
{
    #[\NoDiscard]
    public static function fromUri(string $requestUri): string
    {
        $asciiUri = preg_replace_callback(
            '/[^\x00-\x7F]+/',
            static fn (array $matches): string => rawurlencode($matches[0]),
            $requestUri
        ) ?? $requestUri;

        return Uri\Rfc3986\Uri::parse($asciiUri)?->getPath() ?? '';
    }
}
