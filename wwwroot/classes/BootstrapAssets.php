<?php

declare(strict_types=1);

require_once __DIR__ . '/StaticAsset.php';

final class BootstrapAssets
{
    public const VERSION = '5.3.8';
    public const POPPER_VERSION = '2.11.8';

    public static function stylesheetUrl(): string
    {
        return StaticAsset::url('/lib/bootstrap/' . self::VERSION . '/css/bootstrap.min.css');
    }

    public static function scriptUrl(): string
    {
        return StaticAsset::url('/lib/bootstrap/' . self::VERSION . '/js/bootstrap.min.js');
    }

    public static function popperScriptUrl(): string
    {
        return StaticAsset::url('/lib/popper/' . self::POPPER_VERSION . '/popper.min.js');
    }
}
