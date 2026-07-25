<?php

declare(strict_types=1);

final readonly class SiteUrl
{
    private const string ORIGIN = 'https://psn100.net';

    /**
     * Resolve a site-relative path (or pass through an already-absolute URL)
     * for consumers that require absolute URLs, such as Open Graph tags.
     */
    #[\NoDiscard]
    public static function absolute(string $pathOrUrl): string
    {
        if (str_starts_with($pathOrUrl, 'https://') || str_starts_with($pathOrUrl, 'http://')) {
            return $pathOrUrl;
        }

        if ($pathOrUrl === '' || $pathOrUrl === '/') {
            return self::ORIGIN . '/';
        }

        // Concatenate rather than Uri::withPath() so filenames with spaces or
        // other non-URI characters from persisted icon URLs do not throw.
        return self::ORIGIN . (str_starts_with($pathOrUrl, '/') ? $pathOrUrl : '/' . $pathOrUrl);
    }
}
