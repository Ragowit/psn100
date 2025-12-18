<?php

declare(strict_types=1);

interface ImageProcessorInterface
{
    public function isSupported(): bool;

    public function createImageFromString(string $contents): ?\GdImage;

    public function destroyImage(\GdImage $image): void;

    public function getWidth(\GdImage $image): int;

    public function getHeight(\GdImage $image): int;

    public function isTrueColor(\GdImage $image): bool;

    public function convertPaletteToTrueColor(\GdImage $image): void;

    public function getColorAt(\GdImage $image, int $x, int $y): int;

    /**
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    public function getColorComponents(\GdImage $image, int $color): array;
}

final class GdImageProcessor implements ImageProcessorInterface
{
    #[\Override]
    public function isSupported(): bool
    {
        return extension_loaded('gd');
    }

    #[\Override]
    public function createImageFromString(string $contents): ?\GdImage
    {
        try {
            $image = @imagecreatefromstring($contents);
        } catch (\ValueError) {
            return null;
        }

        if ($image === false) {
            return null;
        }

        return $image;
    }

    #[\Override]
    public function destroyImage(\GdImage $image): void
    {
        // No-op: GdImage objects are automatically cleaned up in PHP 8.0+
    }

    #[\Override]
    public function getWidth(\GdImage $image): int
    {
        return imagesx($image);
    }

    #[\Override]
    public function getHeight(\GdImage $image): int
    {
        return imagesy($image);
    }

    #[\Override]
    public function isTrueColor(\GdImage $image): bool
    {
        return imageistruecolor($image);
    }

    #[\Override]
    public function convertPaletteToTrueColor(\GdImage $image): void
    {
        @imagepalettetotruecolor($image);
    }

    #[\Override]
    public function getColorAt(\GdImage $image, int $x, int $y): int
    {
        return imagecolorat($image, $x, $y);
    }

    #[\Override]
    public function getColorComponents(\GdImage $image, int $color): array
    {
        return imagecolorsforindex($image, $color);
    }
}
