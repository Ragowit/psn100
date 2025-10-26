<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class UtilityTest extends TestCase
{
    public function testSlugifyNormalizesTextAndRemovesSpecialCharacters(): void
    {
        $utility = new Utility();

        $slug = $utility->slugify('  God of War: Ragnarök & Friends  ');

        $this->assertSame('god-of-war-ragnarok-and-friends', $slug);
    }

    public function testSlugifyReplacesPercentAndWhitespaceWithHyphens(): void
    {
        $utility = new Utility();

        $slug = $utility->slugify('100% Ready for 2024');

        $this->assertSame('100percent-ready-for-2024', $slug);
    }

    public function testGetCountryNameReturnsLocaleDisplayNameWhenAvailable(): void
    {
        $utility = new Utility();

        $this->assertSame('United States', $utility->getCountryName(' us '));
    }

    public function testGetCountryNameReturnsUnknownWhenInputEmpty(): void
    {
        $utility = new Utility();

        $this->assertSame('Unknown', $utility->getCountryName(''));
    }

    public function testGetCountryNameFallsBackToUppercaseCodeWhenLocaleReturnsEmpty(): void
    {
        $utility = new Utility();

        $this->assertSame('ABC', $utility->getCountryName('abc'));
    }
}
