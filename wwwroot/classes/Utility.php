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
    private static bool $slugTransliteratorInitialized = false;

    public function slugify(?string $text): string
    {
        $text = $text ?? '';

        $normalizedWhitespace = preg_replace('/\s+/', ' ', $text) ?? $text;
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
        $slug = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;

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
        if (self::$slugTransliteratorInitialized) {
            return self::$slugTransliterator;
        }

        self::$slugTransliteratorInitialized = true;

        if (!class_exists(\Transliterator::class)) {
            return null;
        }

        $transliterator = \Transliterator::createFromRules(self::SLUG_TRANSLITERATOR_RULES);

        self::$slugTransliterator = $transliterator instanceof \Transliterator ? $transliterator : null;

        return self::$slugTransliterator;
    }
}
