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

    public function testArcadeArchives2TitlesGetAColonAfterTheSeriesPrefix(): void
    {
        $formatted = $this->formatter->format('Arcade Archives 2 Ace Driver');

        $this->assertSame('Arcade Archives 2: Ace Driver', $formatted);
    }

    public function testArcadeArchivesTitlesGetAColonAfterTheSeriesPrefix(): void
    {
        $formatted = $this->formatter->format('Arcade Archives Ace Driver');

        $this->assertSame('Arcade Archives: Ace Driver', $formatted);
    }

    public function testConsoleArchivesTitlesGetAColonAfterTheSeriesPrefix(): void
    {
        $formatted = $this->formatter->format('Console Archives Cool Boarders');

        $this->assertSame('Console Archives: Cool Boarders', $formatted);
    }

    public function testArchiveSeriesSubtitleUsesHyphenAfterPrefixColon(): void
    {
        $this->assertSame(
            'Console Archives: Rhapsody II - Ballad of the Little Princess',
            $this->formatter->format(
                'Console Archives Rhapsody II: Ballad of the Little Princess'
            ),
        );
    }

    public function testSubtitleColonPrecedesAdditionalContentHyphen(): void
    {
        $this->assertSame(
            'Pathfinder: Kingmaker - Definitive Edition',
            $this->formatter->format('Pathfinder: Kingmaker: Definitive Edition'),
        );
        $this->assertSame(
            "Tom Clancy's Rainbow Six: Siege - Operation Grim Sky",
            $this->formatter->format("Tom Clancy's Rainbow Six: Siege - Operation Grim Sky"),
        );
        $this->assertSame(
            'The Witcher 3: Wild Hunt - Heart of Stone',
            $this->formatter->format('The Witcher 3: Wild Hunt - Heart of Stone'),
        );
    }

    public function testSeparatorSpacingAndOrderAreNormalized(): void
    {
        $this->assertSame('Game: Title - Test', $this->formatter->format('Game - Title - Test'));
        $this->assertSame('Game: Title - Test', $this->formatter->format('Game - Title: Test'));
        $this->assertSame('Game: Title - Test', $this->formatter->format('Game : Title- Test'));
        $this->assertSame('Game: Title-Test', $this->formatter->format('Game : Title-Test'));
        $this->assertSame('Game: Title', $this->formatter->format('Game -Title-'));
    }

    public function testSeparatorConventionResetsAfterAdditionalContentHyphen(): void
    {
        $this->assertSame(
            'Resident Evil: Revelations 2 - Extra Episode 2: Little Miss',
            $this->formatter->format(
                'Resident Evil: Revelations 2 - Extra Episode 2: Little Miss'
            ),
        );
    }

    public function testArchiveSeriesTitlesThatAlreadyHaveAColonAreUnchanged(): void
    {
        $this->assertSame(
            'Arcade Archives 2: Ace Driver',
            $this->formatter->format('Arcade Archives 2: Ace Driver')
        );
        $this->assertSame(
            'Arcade Archives: Ace Driver',
            $this->formatter->format('Arcade Archives: Ace Driver')
        );
        $this->assertSame(
            'Console Archives: Cool Boarders',
            $this->formatter->format('Console Archives: Cool Boarders')
        );
    }

    public function testArcadeArchives2IsPreferredOverArcadeArchives(): void
    {
        $formatted = $this->formatter->format('arcade archives 2 raiden fighters');

        $this->assertSame('Arcade Archives 2: Raiden Fighters', $formatted);
    }

}
