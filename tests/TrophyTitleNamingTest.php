<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/ThirtyMinuteCronJob.php';

final class TrophyTitleNamingTest extends TestCase
{
    private ThirtyMinuteCronJob $cronJob;
    private ReflectionMethod $sanitizeMethod;
    private ReflectionMethod $titleCaseMethod;

    protected function setUp(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $trophyCalculator = new TrophyCalculator($database);
        $logger = new Psn100Logger($database);

        $this->cronJob = new ThirtyMinuteCronJob($database, $trophyCalculator, $logger, 1);

        $this->sanitizeMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'sanitizeTrophyTitleName');
        $this->sanitizeMethod->setAccessible(true);

        $this->titleCaseMethod = new ReflectionMethod(ThirtyMinuteCronJob::class, 'convertToApaTitleCase');
        $this->titleCaseMethod->setAccessible(true);
    }

    private function formatTitle(string $name): string
    {
        $sanitized = $this->sanitizeMethod->invoke($this->cronJob, $name);

        return $this->titleCaseMethod->invoke($this->cronJob, $sanitized);
    }

    public function testSanitizeRemovesDecorationsFromTrophySetTitles(): void
    {
        $formatted = $this->formatTitle(' Trophy Set - Ratchet & Clank™ Trophy Set. ');

        $this->assertSame('Ratchet & Clank', $formatted);
    }

    public function testSanitizeRemovesTrophysetPrefix(): void
    {
        $formatted = $this->formatTitle('Trophyset: Horizon Forbidden West');

        $this->assertSame('Horizon Forbidden West', $formatted);
    }

    public function testHyphenSeparatorsAreConvertedToColons(): void
    {
        $formatted = $this->formatTitle("Marvel's Spider-Man - Miles Morales");

        $this->assertSame("Marvel's Spider-Man: Miles Morales", $formatted);
    }

    public function testEnDashAndTrophiesSuffixAreNormalized(): void
    {
        $formatted = $this->formatTitle("Journey – Collector's Edition Trophies");

        $this->assertSame("Journey: Collector's Edition", $formatted);
    }

    public function testApaTitleCaseLeavesSmallWordsLowercase(): void
    {
        $formatted = $this->formatTitle('return of the jedi and the sith');

        $this->assertSame('Return of the Jedi and the Sith', $formatted);
    }
}
