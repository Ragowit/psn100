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

    public function slugify(?string $text): string
    {
        $text = $text ?? '';

        $normalizedWhitespace = preg_replace('/\s+/', ' ', $text);
        if (!is_string($normalizedWhitespace)) {
            $normalizedWhitespace = $text;
        }
        $text = trim($normalizedWhitespace);
        $text = str_replace(['&', '%', ' - '], ['and', 'percent', ' '], $text);

        $transliterator = self::getSlugTransliterator();

        if ($transliterator instanceof \Transliterator) {
            $slug = $transliterator->transliterate($text);
            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        }

        $text = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $text);
        if (!is_string($slug)) {
            $slug = $text;
        }

        return trim($slug, '-');
    }

    public function getCountryName(?string $countryCode): string
    {
        $countryCode = strtoupper(trim((string) ($countryCode ?? '')));

        if ($countryCode === '') {
            return 'Unknown';
        }

        if (class_exists('Locale')) {
            try {
                $name = \Locale::getDisplayRegion('-' . $countryCode, 'en');
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            } catch (\Throwable $exception) {
                // Ignore and fall back to returning the country code.
            }
        }

        return $countryCode;
    }

    private static function getSlugTransliterator(): ?\Transliterator
    {
        static $cached = null;
        static $initialized = false;

        if ($initialized) {
            return $cached;
        }

        $initialized = true;

        if (!class_exists('Transliterator')) {
            return null;
        }

        $cached = \Transliterator::createFromRules(self::SLUG_TRANSLITERATOR_RULES);

        return $cached instanceof \Transliterator ? $cached : null;
    }
}
