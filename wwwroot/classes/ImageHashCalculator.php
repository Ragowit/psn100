<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageProcessor.php';

final class ImageHashCalculator
{
    private const GD_ALPHA_MAX = 127;
    private const RGBA_CHANNEL_MAX = 255;

    private ImageProcessorInterface $imageProcessor;

    public function __construct(?ImageProcessorInterface $imageProcessor = null)
    {
        $this->imageProcessor = $imageProcessor ?? new GdImageProcessor();
    }

    public function calculate(string $contents): ?string
    {
        if ($contents === '') {
            return null;
        }

        if (!$this->imageProcessor->isSupported()) {
            return null;
        }

        $image = $this->imageProcessor->createImageFromString($contents);

        if ($image === null) {
            return null;
        }

        try {
            $width = $this->imageProcessor->getWidth($image);
            $height = $this->imageProcessor->getHeight($image);

            if ($width <= 0 || $height <= 0) {
                return null;
            }

            // Ensure stable pixel representation
            if (!$this->imageProcessor->isTrueColor($image)) {
                $this->imageProcessor->convertPaletteToTrueColor($image);
            }

            // Optional but helps ensure GD keeps alpha as expected
            // (No harm for hashing; makes behavior more predictable.)
            imagesavealpha($image, true);
            imagealphablending($image, false);

            return $this->hashRgbaPixels($image, $width, $height);
        } finally {
            $this->imageProcessor->destroyImage($image);
        }
    }

    private function hashRgbaPixels(\GdImage $image, int $width, int $height): string
    {
        $ctx = hash_init('md5');

        for ($y = 0; $y < $height; $y++) {
            $row = '';

            for ($x = 0; $x < $width; $x++) {
                $c = imagecolorat($image, $x, $y);

                // GD truecolor integer is 0xAARRGGBB where AA is 0..127 (0=opaque, 127=transparent)
                $gdAlpha = ($c >> 24) & 0x7F;

                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;

                $a = $this->convertGdAlphaToStandard($gdAlpha); // 0..255 (255=opaque)

                // 4 bytes per pixel, stable RGBA
                $row .= pack('CCCC', $r, $g, $b, $a);
            }

            // Stream into MD5 context to avoid a huge buffer
            hash_update($ctx, $row);
        }

        return hash_final($ctx);
    }

    private function convertGdAlphaToStandard(int $gdAlpha): int
    {
        // GD: 0 = fully opaque, 127 = fully transparent
        // Standard: 255 = fully opaque, 0 = fully transparent
        $normalized = (int) round(
            (self::GD_ALPHA_MAX - $gdAlpha) * self::RGBA_CHANNEL_MAX / self::GD_ALPHA_MAX
        );

        return max(0, min(self::RGBA_CHANNEL_MAX, $normalized));
    }
}