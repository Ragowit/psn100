<?php

declare(strict_types=1);

class Utility
{
    private const SLUG_TRANSLITERATOR_RULES = ':: Any-Latin;'
        . ':: NFD;'
        . ':: [:Nonspacing Mark:] Remove;'
        . ':: NFC;'
        . ':: [:Punctuation:] Remove;'
        . ':: Lower();'
        . '[:Separator:] > \'-\'';
    private static ?\Transliterator $slugTransliterator = null;

    public function slugify(?string $text): string
    {
        $text = $text ?? '';

        $normalizedWhitespace = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($normalizedWhitespace);
        $text = str_replace(['&', '%', ' - '], ['and', 'percent', ' '], $text);

        $slug = self::getSlugTransliterator()->transliterate($text);
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        $text = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;

        return trim($slug, '-');
    }

    public function getCountryName(?string $countryCode): string
    {
        $countryCode = strtoupper(trim((string) ($countryCode ?? '')));

        if ($countryCode === '') {
            return 'Unknown';
        }

        $name = \Locale::getDisplayRegion('-' . $countryCode, 'en');
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return $countryCode;
    }

    private static function getSlugTransliterator(): \Transliterator
    {
        return self::$slugTransliterator
            ??= \Transliterator::createFromRules(self::SLUG_TRANSLITERATOR_RULES)
                ?? throw new \RuntimeException('Unable to create slug transliterator.');
    }
}
