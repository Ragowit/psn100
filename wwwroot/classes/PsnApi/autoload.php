<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'PsnApi\\';
    $prefixLength = strlen($prefix);

    if (strncmp($class, $prefix, $prefixLength) !== 0) {
        return;
    }

    $relativeClass = substr($class, $prefixLength);
    if ($relativeClass === false) {
        return;
    }

    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $file = __DIR__ . DIRECTORY_SEPARATOR . $relativePath;

    if (is_file($file)) {
        require_once $file;
    }
});
