<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageProcessor.php';

final class ImageHashCalculator
{
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

            // Important: Make sure we are always working with TrueColor
            if (!$this->imageProcessor->isTrueColor($image)) {
                $this->imageProcessor->convertPaletteToTrueColor($image);
            }

            // Always build the buffer with RGBA components to ensure consistent hashing
            $buffer = $this->buildPixelBuffer($image, $width, $height);

            return md5($buffer);
        } finally {
            $this->imageProcessor->destroyImage($image);
        }
    }

    private function buildPixelBuffer(
        \GdImage $image,
        int $width,
        int $height
    ): string {
        $buffer = '';

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = $this->imageProcessor->getColorAt($image, $x, $y);
                $components = $this->imageProcessor->getColorComponents($image, $color);

                // R, G, B is added in sequence, and we always include the alpha component (even if it's 0) to maintain consistent buffer length
                $buffer .= chr($components['red'] ?? 0);
                $buffer .= chr($components['green'] ?? 0);
                $buffer .= chr($components['blue'] ?? 0);

                /**
                 * Convert GD:s alpha (0-127, where 0 is invisible) to standard (0-255, where 255 is invisible).
                 * We ALWAYS include this value to keep the same data length in the buffer.
                 */
                $alphaGD = $components['alpha'] ?? 0;
                $alphaStandard = (int) round((127 - $alphaGD) * 255 / 127);
                $alphaStandard = max(0, min(255, $alphaStandard));

                $buffer .= chr($alphaStandard);
            }
        }

        return $buffer;
    }
}
