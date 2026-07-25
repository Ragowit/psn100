<?php

declare(strict_types=1);

final readonly class StaticAsset
{
    #[\NoDiscard]
    public static function url(string $webPath): string
    {
        $webPath = '/' . ltrim($webPath, '/');
        $fullPath = dirname(__DIR__) . $webPath;

        if (!is_file($fullPath)) {
            return $webPath;
        }

        return $webPath . '?v=' . filemtime($fullPath);
    }
}
