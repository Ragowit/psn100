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
    #[\Override]
    public function isSupported(): bool
    {
        return extension_loaded('gd');
    }

    #[\Override]
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

    #[\Override]
    public function destroyImage(mixed $image): void
    {
        if (is_resource($image) || $image instanceof \GdImage) {
            imagedestroy($image);
        }
    }

    #[\Override]
    public function getWidth(mixed $image): int
    {
        return imagesx($image);
    }

    #[\Override]
    public function getHeight(mixed $image): int
    {
        return imagesy($image);
    }

    #[\Override]
    public function isTrueColor(mixed $image): bool
    {
        return imageistruecolor($image);
    }

    #[\Override]
    public function convertPaletteToTrueColor(mixed $image): void
    {
        @imagepalettetotruecolor($image);
    }

    #[\Override]
    public function getColorAt(mixed $image, int $x, int $y): int
    {
        return imagecolorat($image, $x, $y);
    }

    #[\Override]
    public function getColorComponents(mixed $image, int $color): array
    {
        return imagecolorsforindex($image, $color);
    }
}
