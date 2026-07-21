<?php

declare(strict_types=1);

class Utility
{
    private const string SLUG_TRANSLITERATOR_RULES = ':: Any-Latin;'
        . ':: NFD;'
        . ':: [:Nonspacing Mark:] Remove;'
        . ':: NFC;'
        . ':: [:Punctuation:] Remove;'
        . ':: Lower();'
        . '[:Separator:] > \'-\'';
    private static ?\Transliterator $slugTransliterator = null;

    #[\NoDiscard]
    public function slugify(?string $text): string
    {
        $text = ($text ?? '')
            |> (fn(string $value): string => preg_replace('/\s+/', ' ', $value) ?? $value)
            |> trim(...)
            |> (fn(string $value): string => str_replace(['&', '%', ' - '], ['and', 'percent', ' '], $value));

        $slug = self::getSlugTransliterator()->transliterate($text);
        if (is_string($slug) && $slug !== '') {
            return $slug;
        }

        $slug = $text
            |> strtolower(...)
            |> (fn(string $value): string => preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value)
            |> (fn(string $value): string => trim($value, '-'));

        return $slug;
    }

    #[\NoDiscard]
    public function getCountryName(?string $countryCode): string
    {
        $countryCode = ($countryCode ?? '')
            |> trim(...)
            |> strtoupper(...);

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
