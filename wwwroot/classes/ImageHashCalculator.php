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

    /**
     * Standard MD5 hash based on raw RGBA pixel data.
     * Ideal for Trophies where 100% binary accuracy is required.
     */
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

    /**
     * Difference Hash (dHash) - 16x16 resolution.
     * Compares adjacent pixels to capture structural gradients.
     * This is much better at distinguishing different subjects like an Orb vs a Bear.
     */
    public function calculatePHash(string $contents): ?string
    {
        if ($contents === '' || !$this->imageProcessor->isSupported()) {
            return null;
        }

        $image = $this->imageProcessor->createImageFromString($contents);
        if ($image === null) return null;

        try {
            // For dHash 16x16, we need 17x16 pixels to compare horizontally
            $width = 17;
            $height = 16;
            $sample = imagecreatetruecolor($width, $height);
            
            $white = imagecolorallocate($sample, 255, 255, 255);
            imagefill($sample, 0, 0, $white);
            
            imagecopyresampled($sample, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

            $hashHex = '';
            $binaryChunk = '';

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < 16; $x++) {
                    // Get luminance of current pixel and the one to its right
                    $rgbLeft = imagecolorat($sample, $x, $y);
                    $rgbRight = imagecolorat($sample, $x + 1, $y);

                    $lLeft = (($rgbLeft >> 16) & 0xFF) * 0.299 + (($rgbLeft >> 8) & 0xFF) * 0.587 + ($rgbLeft & 0xFF) * 0.114;
                    $lRight = (($rgbRight >> 16) & 0xFF) * 0.299 + (($rgbRight >> 8) & 0xFF) * 0.587 + ($rgbRight & 0xFF) * 0.114;

                    // The bit is 1 if the left pixel is brighter than the right
                    $binaryChunk .= ($lLeft > $lRight) ? '1' : '0';

                    if (strlen($binaryChunk) === 4) {
                        $hashHex .= dechex(bindec($binaryChunk));
                        $binaryChunk = '';
                    }
                }
            }

            imagedestroy($sample);
            return $hashHex;

        } finally {
            $this->imageProcessor->destroyImage($image);
        }
    }

    public function getHammingDistance(string $hash1, string $hash2): int
    {
        if (strlen($hash1) !== strlen($hash2)) return 256;
        $distance = 0;
        for ($i = 0; $i < strlen($hash1); $i++) {
            $xor = hexdec($hash1[$i]) ^ hexdec($hash2[$i]);
            while ($xor > 0) {
                if ($xor & 1) $distance++;
                $xor >>= 1;
            }
        }
        return $distance;
    }

    /**
     * Internal helper to iterate through pixels and update hash context.
     */
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

    /**
     * Converts GD's 0..127 alpha (0=opaque, 127=transparent) to standard 0..255 (0=transparent, 255=opaque).
     */
    private function convertGdAlphaToStandard(int $gdAlpha): int
    {
        $normalized = (int) round(
            (self::GD_ALPHA_MAX - $gdAlpha) * self::RGBA_CHANNEL_MAX / self::GD_ALPHA_MAX
        );

        return max(0, min(self::RGBA_CHANNEL_MAX, $normalized));
    }
}