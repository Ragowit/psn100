<?php

declare(strict_types=1);

final readonly class Html
{
    #[\NoDiscard]
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
