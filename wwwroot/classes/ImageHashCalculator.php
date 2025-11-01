<?php

declare(strict_types=1);

class ImageHashCalculator
{
    public static function calculate(string $contents): ?string
    {
        if ($contents === '') {
            return null;
        }

        if (!extension_loaded('gd')) {
            return null;
        }

        try {
            $image = @imagecreatefromstring($contents);
        } catch (\ValueError $exception) {
            return null;
        }

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($image);

            return null;
        }

        if (!imageistruecolor($image)) {
            @imagepalettetotruecolor($image);
        }

        $hasTransparency = false;

        for ($y = 0; $y < $height && !$hasTransparency; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                $components = imagecolorsforindex($image, $color);

                if (($components['alpha'] ?? 0) > 0) {
                    $hasTransparency = true;
                    break;
                }
            }
        }

        $buffer = '';

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                $components = imagecolorsforindex($image, $color);

                $buffer .= chr($components['red']);
                $buffer .= chr($components['green']);
                $buffer .= chr($components['blue']);

                if ($hasTransparency) {
                    $alpha = (int) round(($components['alpha'] ?? 0) * 255 / 127);
                    if ($alpha < 0) {
                        $alpha = 0;
                    } elseif ($alpha > 255) {
                        $alpha = 255;
                    }

                    $buffer .= chr($alpha);
                }
            }
        }

        imagedestroy($image);

        if ($buffer === '') {
            return null;
        }

        return md5($buffer);
    }
}
