<?php
function slugify($text)
{
    $text = $text ?? "";

    $text = trim(preg_replace('/\s+/', ' ', $text)); // Replace new lines with space
    $text = str_replace("&", "and", $text);
    $text = str_replace("%", "percent", $text);
    $text = str_replace(" - ", " ", $text);

    return \Transliterator::createFromRules(
        ':: Any-Latin;'
        . ':: NFD;'
        . ':: [:Nonspacing Mark:] Remove;'
        . ':: NFC;'
        . ':: [:Punctuation:] Remove;'
        . ':: Lower();'
        . '[:Separator:] > \'-\''
    )->transliterate($text);
}
