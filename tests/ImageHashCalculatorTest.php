<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/ImageProcessor.php';
require_once __DIR__ . '/../wwwroot/classes/ImageHashCalculator.php';

final class ImageHashCalculatorTest extends TestCase
{
    public function testCalculateReturnsNullWhenContentsEmpty(): void
    {
        $calculator = new ImageHashCalculator(new FakeImageProcessor());

        $this->assertSame(null, $calculator->calculate(''));
    }

    public function testCalculateReturnsNullWhenProcessorUnsupported(): void
    {
        $calculator = new ImageHashCalculator(new FakeImageProcessor(supported: false));

        $this->assertSame(null, $calculator->calculate('image-data'));
    }

    public function testCalculateReturnsNullWhenImageCreationFails(): void
    {
        $calculator = new ImageHashCalculator(new FakeImageProcessor(createSucceeds: false));

        $this->assertSame(null, $calculator->calculate('image-data'));
    }

    public function testCalculateReturnsHashForOpaqueImage(): void
    {
        $pixels = [
            [
                ['red' => 0, 'green' => 1, 'blue' => 2, 'alpha' => 0],
                ['red' => 3, 'green' => 4, 'blue' => 5, 'alpha' => 0],
            ],
        ];
        $calculator = new ImageHashCalculator(new FakeImageProcessor(pixels: $pixels));

        $buffer = '';
        foreach ($pixels as $row) {
            foreach ($row as $pixel) {
                $buffer .= chr($pixel['red']);
                $buffer .= chr($pixel['green']);
                $buffer .= chr($pixel['blue']);
            }
        }

        $this->assertSame(md5($buffer), $calculator->calculate('image-data'));
    }

    public function testCalculateIncludesAlphaWhenTransparencyDetected(): void
    {
        $pixels = [
            [
                ['red' => 10, 'green' => 20, 'blue' => 30, 'alpha' => 5],
                ['red' => 40, 'green' => 50, 'blue' => 60, 'alpha' => 0],
            ],
        ];
        $calculator = new ImageHashCalculator(new FakeImageProcessor(pixels: $pixels));

        $buffer = '';
        foreach ($pixels as $row) {
            foreach ($row as $pixel) {
                $buffer .= chr($pixel['red']);
                $buffer .= chr($pixel['green']);
                $buffer .= chr($pixel['blue']);

                $alpha = (int) round(($pixel['alpha'] ?? 0) * 255 / 127);
                $alpha = max(0, min(255, $alpha));
                $buffer .= chr($alpha);
            }
        }

        $this->assertSame(md5($buffer), $calculator->calculate('image-data'));
    }
}

final class FakeImageProcessor implements ImageProcessorInterface
{
    private bool $supported;

    private bool $createSucceeds;

    private int $width;

    private int $height;

    private array $pixels;

    private bool $trueColor;

    private object $imageHandle;

    public function __construct(
        bool $supported = true,
        bool $createSucceeds = true,
        int $width = 1,
        int $height = 1,
        array $pixels = [],
        bool $trueColor = true
    ) {
        $this->supported = $supported;
        $this->createSucceeds = $createSucceeds;
        $this->trueColor = $trueColor;
        $this->imageHandle = new stdClass();

        if ($pixels !== []) {
            $this->pixels = $pixels;
            $this->height = count($pixels);
            $this->width = count($pixels[0]);
        } else {
            $this->pixels = [];
            $this->width = $width;
            $this->height = $height;
        }
    }

    #[\Override]
    public function isSupported(): bool
    {
        return $this->supported;
    }

    #[\Override]
    public function createImageFromString(string $contents): mixed
    {
        if (!$this->createSucceeds) {
            return null;
        }

        return $this->imageHandle;
    }

    #[\Override]
    public function destroyImage(mixed $image): void
    {
        // Nothing to clean up in the fake processor.
    }

    #[\Override]
    public function getWidth(mixed $image): int
    {
        return $this->width;
    }

    #[\Override]
    public function getHeight(mixed $image): int
    {
        return $this->height;
    }

    #[\Override]
    public function isTrueColor(mixed $image): bool
    {
        return $this->trueColor;
    }

    #[\Override]
    public function convertPaletteToTrueColor(mixed $image): void
    {
        $this->trueColor = true;
    }

    #[\Override]
    public function getColorAt(mixed $image, int $x, int $y): int
    {
        return $y * max(1, $this->width) + $x;
    }

    #[\Override]
    public function getColorComponents(mixed $image, int $color): array
    {
        if ($this->pixels === []) {
            return ['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 0];
        }

        $width = max(1, $this->width);
        $y = intdiv($color, $width);
        $x = $color % $width;

        return $this->pixels[$y][$x];
    }
}
