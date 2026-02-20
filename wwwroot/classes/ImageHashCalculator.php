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

            $buffer = $this->getRawImageData($image);

            return $buffer ? md5($buffer) : null;
        } finally {
            $this->imageProcessor->destroyImage($image);
        }
    }

    private function getRawImageData(\GdImage $image): ?string
    {
        ob_start();
        $success = imagebmp($image, null, false);
        $data = ob_get_clean();

        return $success ? $data : null;
    }
}
