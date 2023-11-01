<?php
function slugify($text)
{
    $text = str_replace("&", "and", $text);
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
