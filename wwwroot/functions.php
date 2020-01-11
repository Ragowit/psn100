<?php
function slugify($text)
{
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
