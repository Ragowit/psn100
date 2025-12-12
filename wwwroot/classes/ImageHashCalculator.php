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

            if (!$this->imageProcessor->isTrueColor($image)) {
                $this->imageProcessor->convertPaletteToTrueColor($image);
            }

            $hasTransparency = $this->hasTransparency($image, $width, $height);
            $buffer = $this->buildPixelBuffer($image, $width, $height, $hasTransparency);

            if ($buffer === '') {
                return null;
            }

            return md5($buffer);
        } finally {
            $this->imageProcessor->destroyImage($image);
        }
    }

    private function hasTransparency(\GdImage $image, int $width, int $height): bool
    {
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = $this->imageProcessor->getColorAt($image, $x, $y);
                $components = $this->imageProcessor->getColorComponents($image, $color);

                if (($components['alpha'] ?? 0) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function buildPixelBuffer(
        \GdImage $image,
        int $width,
        int $height,
        bool $hasTransparency
    ): string {
        $buffer = '';

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = $this->imageProcessor->getColorAt($image, $x, $y);
                $components = $this->imageProcessor->getColorComponents($image, $color);

                $buffer .= chr($components['red'] ?? 0);
                $buffer .= chr($components['green'] ?? 0);
                $buffer .= chr($components['blue'] ?? 0);

                if ($hasTransparency) {
                    $alpha = (int) round(($components['alpha'] ?? 0) * 255 / 127);
                    $alpha = max(0, min(255, $alpha));

                    $buffer .= chr($alpha);
                }
            }
        }

        return $buffer;
    }
}
