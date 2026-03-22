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
     * Perceptual Hash (pHash) for Avatars.
     * Generates a 16-character HEX string based on the image structure.
     * Resistant to minor compression artifacts and metadata changes.
     */
    public function calculatePHash(string $contents): ?string
    {
        if ($contents === '' || !$this->imageProcessor->isSupported()) {
            return null;
        }

        $image = $this->imageProcessor->createImageFromString($contents);
        if ($image === null) return null;

        try {
            // 1. Create a small 8x8 grayscale thumbnail to ignore noise/fine details
            $sample = imagecreatetruecolor(8, 8);
            
            // Fill with white background to handle transparency consistently
            $white = imagecolorallocate($sample, 255, 255, 255);
            imagefill($sample, 0, 0, $white);
            
            imagecopyresampled($sample, $image, 0, 0, 0, 0, 8, 8, imagesx($image), imagesy($image));

            $pixels = [];
            $totalLuminance = 0;

            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $rgb = imagecolorat($sample, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;

                    // Calculate perceived brightness (Luminance) using BT.601 formula
                    $gray = (int)($r * 0.299 + $g * 0.587 + $b * 0.114);
                    $pixels[] = $gray;
                    $totalLuminance += $gray;
                }
            }

            $avg = $totalLuminance / 64;
            $hashBin = '';

            // 2. Generate bit string (1 if pixel >= average, 0 otherwise)
            foreach ($pixels as $pixel) {
                $hashBin .= ($pixel >= $avg) ? '1' : '0';
            }

            // 3. Convert 64-bit binary string to 16-character Hexadecimal
            $pHash = '';
            foreach (str_split($hashBin, 4) as $nibble) {
                $pHash .= dechex(bindec($nibble));
            }

            imagedestroy($sample);
            return $pHash;

        } finally {
            $this->imageProcessor->destroyImage($image);
        }
    }

    /**
     * Calculates the Hamming Distance between two hex hashes.
     * 0 = Identical, 1-3 = Visually very similar, >10 = Different images.
     */
    public function getHammingDistance(string $hash1, string $hash2): int
    {
        if (strlen($hash1) !== strlen($hash2)) return 64;

        // Convert hex to binary strings padded to 64 bits
        $bin1 = str_pad(base_convert($hash1, 16, 2), 64, '0', STR_PAD_LEFT);
        $bin2 = str_pad(base_convert($hash2, 16, 2), 64, '0', STR_PAD_LEFT);

        $distance = 0;
        for ($i = 0; $i < 64; $i++) {
            if ($bin1[$i] !== $bin2[$i]) {
                $distance++;
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