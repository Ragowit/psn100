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
     * * * Generates a high-precision perceptual hash (dHash) for images.
     * Architecture: 152 bits total (38 hex characters)
     * - 64 bits: Luminance Structure (8x8)
     * - 64 bits: Alpha/Transparency Structure (8x8)
     * - 24 bits: Average RGB Color
     * Calculates the perceptual hash of an image from a binary string.
     * * @param string $contents The raw image data.
     * @return string|null The 38-character hex hash, or null on failure.
     */
    public function calculatePHash(string $contents): ?string 
    {
        if ($contents === '') {
            return null;
        }

        // Create image resource from binary string
        $image = imagecreatefromstring($contents);
        if (!$image) {
            return null;
        }

        try {
            // Sampling size: 9x8 pixels to allow for 8 horizontal comparisons per row
            $width = 9; 
            $height = 8;
            $sample = imagecreatetruecolor($width, $height);
            
            // Prepare the sample to handle transparency correctly
            imagealphablending($sample, false);
            imagesavealpha($sample, true);
            
            // Resize original image to the sampling grid
            imagecopyresampled(
                $sample, $image, 
                0, 0, 0, 0, 
                $width, $height, 
                imagesx($image), imagesy($image)
            );

            $lBits = ''; // Luminance gradient bits
            $aBits = ''; // Alpha gradient bits
            $rTotal = 0; $gTotal = 0; $bTotal = 0;

            for ($y = 0; $y < $height; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    // Get RGBA values for current pixel and the pixel to its right
                    $rgbL = imagecolorat($sample, $x, $y);
                    $rgbR = imagecolorat($sample, $x + 1, $y);

                    // Extract Alpha and calculate Luminance (Y) for both pixels
                    // GD Alpha is 0 (opaque) to 127 (transparent)
                    $aL = ($rgbL >> 24) & 0x7F;
                    $rL = ($rgbL >> 16) & 0xFF;
                    $gL = ($rgbL >> 8) & 0xFF;
                    $bL = $rgbL & 0xFF;
                    $yL = ($rL * 0.299 + $gL * 0.587 + $bL * 0.114);
                    
                    $aR = ($rgbR >> 24) & 0x7F;
                    $rR = ($rgbR >> 16) & 0xFF;
                    $gR = ($rgbR >> 8) & 0xFF;
                    $bR = $rgbR & 0xFF;
                    $yR = ($rR * 0.299 + $gR * 0.587 + $bR * 0.114);

                    // Difference Hash Logic: 1 if left is greater than right, else 0
                    $lBits .= ($yL > $yR) ? '1' : '0'; // Structural change in brightness
                    $aBits .= ($aL > $aR) ? '1' : '0'; // Structural change in transparency

                    // Accumulate RGB values for the global color signature
                    $rTotal += $rL;
                    $gTotal += $gL;
                    $bTotal += $bL;
                }
            }

            // Construct final hash: 16 chars (Luminance) + 16 chars (Alpha) + 6 chars (Color)
            $hash = $this->binToHex($lBits);
            $hash .= $this->binToHex($aBits);
            
            // Calculate average color across all 72 sampled pixels
            $hash .= str_pad(dechex((int)($rTotal / 72)), 2, '0', STR_PAD_LEFT);
            $hash .= str_pad(dechex((int)($gTotal / 72)), 2, '0', STR_PAD_LEFT);
            $hash .= str_pad(dechex((int)($bTotal / 72)), 2, '0', STR_PAD_LEFT);

            return $hash;

        } finally {
            // Free up memory
            imagedestroy($image);
            if (isset($sample)) {
                imagedestroy($sample);
            }
        }
    }

    /**
     * Converts a binary string to a hexadecimal string.
     */
    private function binToHex(string $bin): string 
    {
        $hex = '';
        foreach (str_split($bin, 4) as $chunk) {
            $hex .= dechex(bindec($chunk));
        }
        return $hex;
    }

    /**
     * Calculates the Hamming Distance between two hashes.
     * * @param string $h1 First hash (38 chars)
     * @param string $h2 Second hash (38 chars)
     * @return int Number of differing bits. 
     * A distance <= 10 is usually considered the same image.
     */
    public function getHammingDistance(string $h1, string $h2): int 
    {
        if (strlen($h1) !== strlen($h2)) {
            return 152; // Max distance for this specific bit length
        }

        $dist = 0;
        for ($i = 0; $i < strlen($h1); $i++) {
            // XOR the hex nibbles and count set bits
            $xor = hexdec($h1[$i]) ^ hexdec($h2[$i]);
            while ($xor > 0) {
                if ($xor & 1) {
                    $dist++;
                }
                $xor >>= 1;
            }
        }
        return $dist;
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