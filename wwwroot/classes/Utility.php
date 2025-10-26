<?php

declare(strict_types=1);

class Utility
{
    public function slugify(?string $text): string
    {
        $text = $text ?? '';

        $normalizedWhitespace = preg_replace('/\s+/', ' ', $text);
        if (!is_string($normalizedWhitespace)) {
            $normalizedWhitespace = $text;
        }
        $text = trim($normalizedWhitespace);
        $text = str_replace('&', 'and', $text);
        $text = str_replace('%', 'percent', $text);
        $text = str_replace(' - ', ' ', $text);

        $transliterator = class_exists('Transliterator')
            ? \Transliterator::createFromRules(
                ':: Any-Latin;'
                . ':: NFD;'
                . ':: [:Nonspacing Mark:] Remove;'
                . ':: NFC;'
                . ':: [:Punctuation:] Remove;'
                . ':: Lower();'
                . '[:Separator:] > \'-\''
            )
            : false;

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
}
