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
                $buffer .= chr(255);
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

                $alpha = (int) round((127 - ($pixel['alpha'] ?? 0)) * 255 / 127);
                $alpha = max(0, min(255, $alpha));
                $buffer .= chr($alpha);
            }
        }

        $this->assertSame(md5($buffer), $calculator->calculate('image-data'));
    }

    public function testCalculatePHashReturnsNullWhenContentsEmpty(): void
    {
        $calculator = new ImageHashCalculator(new FakeImageProcessor());

        $this->assertSame(null, $calculator->calculatePHash(''));
    }

    public function testCalculatePHashReturnsNullWhenProcessorUnsupported(): void
    {
        $calculator = new ImageHashCalculator(new FakeImageProcessor(supported: false));

        $this->assertSame(null, $calculator->calculatePHash('image-data'));
    }

    public function testCalculatePHashReturnsNullWhenImageCreationFails(): void
    {
        $calculator = new ImageHashCalculator(new FakeImageProcessor(createSucceeds: false));

        $this->assertSame(null, $calculator->calculatePHash('image-data'));
    }

    public function testCalculatePHashUsesImageProcessorForDecoding(): void
    {
        // Regression: calculatePHash previously called imagecreatefromstring()
        // directly, which logged libpng iCCP/sRGB profile warnings. Decoding
        // must go through ImageProcessor (which uses @imagecreatefromstring).
        $source = (string) file_get_contents(__DIR__ . '/../wwwroot/classes/ImageHashCalculator.php');

        $pHashMethodStart = strpos($source, 'function calculatePHash');
        $this->assertTrue($pHashMethodStart !== false);

        $nextMethodStart = strpos($source, "\n    public function ", $pHashMethodStart + 1);
        if ($nextMethodStart === false) {
            $nextMethodStart = strpos($source, "\n    private function ", $pHashMethodStart + 1);
        }
        $this->assertTrue($nextMethodStart !== false);

        $pHashMethod = substr($source, $pHashMethodStart, $nextMethodStart - $pHashMethodStart);
        $this->assertTrue(!str_contains($pHashMethod, 'imagecreatefromstring('));
        $this->assertStringContainsString('createImageFromString($contents)', $pHashMethod);
    }

    public function testCalculatePHashReturnsThirtyEightCharacterHexHash(): void
    {
        $image = imagecreatetruecolor(16, 16);
        $this->assertTrue($image instanceof \GdImage);
        imagefilledrectangle($image, 0, 0, 7, 15, imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, 8, 0, 15, 15, imagecolorallocate($image, 0, 0, 255));

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();

        $hash = (new ImageHashCalculator())->calculatePHash($contents);

        $this->assertTrue(is_string($hash));
        $this->assertSame(38, strlen((string) $hash));
        $this->assertTrue((bool) preg_match('/^[0-9a-f]{38}$/', (string) $hash));
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

    private \GdImage $imageHandle;

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

        if ($pixels !== []) {
            $this->pixels = $pixels;
            $this->height = count($pixels);
            $this->width = count($pixels[0]);
        } else {
            $this->pixels = [];
            $this->width = $width;
            $this->height = $height;
        }

        $this->imageHandle = imagecreatetruecolor(max(1, $this->width), max(1, $this->height));

        if ($this->pixels !== []) {
            imagealphablending($this->imageHandle, false);
            imagesavealpha($this->imageHandle, true);

            foreach ($this->pixels as $y => $row) {
                foreach ($row as $x => $pixel) {
                    $color = (($pixel['alpha'] ?? 0) << 24)
                        | (($pixel['red'] ?? 0) << 16)
                        | (($pixel['green'] ?? 0) << 8)
                        | ($pixel['blue'] ?? 0);

                    imagesetpixel($this->imageHandle, $x, $y, $color);
                }
            }
        }
    }

    #[\Override]
    public function isSupported(): bool
    {
        return $this->supported;
    }

    #[\Override]
    public function createImageFromString(string $contents): ?\GdImage
    {
        if (!$this->createSucceeds) {
            return null;
        }

        return $this->imageHandle;
    }

    #[\Override]
    public function destroyImage(\GdImage $image): void
    {
        // No-op
    }

    #[\Override]
    public function getWidth(\GdImage $image): int
    {
        return $this->width;
    }

    #[\Override]
    public function getHeight(\GdImage $image): int
    {
        return $this->height;
    }

    #[\Override]
    public function isTrueColor(\GdImage $image): bool
    {
        return $this->trueColor;
    }

    #[\Override]
    public function convertPaletteToTrueColor(\GdImage $image): void
    {
        $this->trueColor = true;
    }

    #[\Override]
    public function getColorAt(\GdImage $image, int $x, int $y): int
    {
        return $y * max(1, $this->width) + $x;
    }

    #[\Override]
    public function getColorComponents(\GdImage $image, int $color): array
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
