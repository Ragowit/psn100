<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyTitleNameFormatter.php';

final class TrophyTitleNamingTest extends TestCase
{
    private TrophyTitleNameFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new TrophyTitleNameFormatter();
    }

    public function testSanitizeRemovesDecorationsFromTrophySetTitles(): void
    {
        $formatted = $this->formatter->format(' Trophy Set - Ratchet & Clank™ Trophy Set. ');

        $this->assertSame('Ratchet & Clank', $formatted);
    }

    public function testSanitizeRemovesTrophysetPrefix(): void
    {
        $formatted = $this->formatter->format('Trophyset: Horizon Forbidden West');

        $this->assertSame('Horizon Forbidden West', $formatted);
    }

    public function testSanitizeRemovesTrophySetForPrefix(): void
    {
        $formatted = $this->formatter->format('TROPHY SET FOR FORTNITE');

        $this->assertSame('Fortnite', $formatted);
    }

    public function testSanitizeRemovesTrophysetSuffix(): void
    {
        $formatted = $this->formatter->format('Fortnite Trophyset');

        $this->assertSame('Fortnite', $formatted);
    }

    public function testHyphenSeparatorsAreConvertedToColons(): void
    {
        $formatted = $this->formatter->format("Marvel's Spider-Man - Miles Morales");

        $this->assertSame("Marvel's Spider-Man: Miles Morales", $formatted);
    }

    public function testEnDashAndTrophiesSuffixAreNormalized(): void
    {
        $formatted = $this->formatter->format("Journey – Collector's Edition Trophies");

        $this->assertSame("Journey: Collector's Edition", $formatted);
    }

    public function testExtraSpacingAroundColonsIsNormalized(): void
    {
        $formatted = $this->formatter->format('Bus Simulator : World Tour');

        $this->assertSame('Bus Simulator: World Tour', $formatted);
    }

    public function testApaTitleCaseLeavesSmallWordsLowercase(): void
    {
        $formatted = $this->formatter->format('return of the jedi and the sith');

        $this->assertSame('Return of the Jedi and the Sith', $formatted);
    }

    public function testFormatReturnsEmptyStringForWhitespaceInput(): void
    {
        $this->assertSame('', $this->formatter->format('   '));
    }
}
