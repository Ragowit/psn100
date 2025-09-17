<?php
function slugify($text)
{
    $text = $text ?? "";

    $text = trim(preg_replace('/\s+/', ' ', $text)); // Replace new lines with space
    $text = str_replace("&", "and", $text);
    $text = str_replace("%", "percent", $text);
    $text = str_replace(" - ", " ", $text);

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
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}
