<?php

declare(strict_types=1);

interface ImageProcessorInterface
{
    public function isSupported(): bool;

    /**
     * @return mixed|null
     */
    public function createImageFromString(string $contents);

    public function destroyImage(mixed $image): void;

    public function getWidth(mixed $image): int;

    public function getHeight(mixed $image): int;

    public function isTrueColor(mixed $image): bool;

    public function convertPaletteToTrueColor(mixed $image): void;

    public function getColorAt(mixed $image, int $x, int $y): int;

    /**
     * @return array<string, int>
     */
    public function getColorComponents(mixed $image, int $color): array;
}

final class GdImageProcessor implements ImageProcessorInterface
{
    public function isSupported(): bool
    {
        return extension_loaded('gd');
    }

    public function createImageFromString(string $contents): mixed
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

    public function destroyImage(mixed $image): void
    {
        if (is_resource($image) || $image instanceof \GdImage) {
            imagedestroy($image);
        }
    }

    public function getWidth(mixed $image): int
    {
        return imagesx($image);
    }

    public function getHeight(mixed $image): int
    {
        return imagesy($image);
    }

    public function isTrueColor(mixed $image): bool
    {
        return imageistruecolor($image);
    }

    public function convertPaletteToTrueColor(mixed $image): void
    {
        @imagepalettetotruecolor($image);
    }

    public function getColorAt(mixed $image, int $x, int $y): int
    {
        return imagecolorat($image, $x, $y);
    }

    public function getColorComponents(mixed $image, int $color): array
    {
        return imagecolorsforindex($image, $color);
    }
}
