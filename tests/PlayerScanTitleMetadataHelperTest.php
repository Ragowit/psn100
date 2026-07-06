<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanTitleMetadataHelper.php';

final class PlayerScanTitleMetadataHelperTest extends TestCase
{
    private PlayerScanTitleMetadataHelper $helper;

    protected function setUp(): void
    {
        $this->helper = new PlayerScanTitleMetadataHelper();
    }

    public function testGameTimestampsMatchReturnsTrueForMatchingValidDates(): void
    {
        $result = $this->helper->gameTimestampsMatch(
            '2024-06-15T10:30:00Z',
            '2024-06-15 10:30:00'
        );

        $this->assertTrue($result);
    }

    public function testGameTimestampsMatchReturnsFalseForDifferentValidDates(): void
    {
        $result = $this->helper->gameTimestampsMatch(
            '2024-06-15T10:30:00Z',
            '2024-06-16 10:30:00'
        );

        $this->assertFalse($result);
    }

    public function testGameTimestampsMatchReturnsFalseWhenSonyTimestampIsInvalid(): void
    {
        $result = $this->helper->gameTimestampsMatch(
            'not-a-valid-date',
            'not-a-valid-date'
        );

        $this->assertFalse($result);
    }

    public function testGameTimestampsMatchReturnsFalseWhenBothInvalidButDifferentRawStrings(): void
    {
        $result = $this->helper->gameTimestampsMatch(
            'not-a-valid-date',
            'also-not-valid'
        );

        $this->assertFalse($result);
    }

    public function testGameTimestampsMatchReturnsFalseWhenDatabaseTimestampIsInvalid(): void
    {
        $result = $this->helper->gameTimestampsMatch(
            '2024-06-15T10:30:00Z',
            'not-a-valid-date'
        );

        $this->assertFalse($result);
    }

    public function testFormatDateTimeForDatabaseReturnsFormattedSonyTimestamp(): void
    {
        $result = $this->helper->formatDateTimeForDatabase('2024-06-15T10:30:00Z');

        $this->assertSame('2024-06-15 10:30:00', $result);
    }

    public function testFormatDateTimeForDatabaseReturnsNullForInvalidTimestamp(): void
    {
        $result = $this->helper->formatDateTimeForDatabase('not-a-valid-date');

        $this->assertSame(null, $result);
    }

    public function testFormatDateTimeForDatabaseReturnsNullForEmptyString(): void
    {
        $result = $this->helper->formatDateTimeForDatabase('');

        $this->assertSame(null, $result);
    }

    public function testShouldRetryInvalidTitleLastUpdatedDateReturnsTrueOnFirstAttempt(): void
    {
        $retryTracker = [];

        $result = $this->helper->shouldRetryInvalidTitleLastUpdatedDate(
            $retryTracker,
            'ExampleUser',
            'NPWR12345_00'
        );

        $this->assertTrue($result);
    }

    public function testShouldRetryInvalidTitleLastUpdatedDateReturnsFalseAfterMarkedRetried(): void
    {
        $retryTracker = [
            'ExampleUser:NPWR12345_00' => true,
        ];

        $result = $this->helper->shouldRetryInvalidTitleLastUpdatedDate(
            $retryTracker,
            'ExampleUser',
            'NPWR12345_00'
        );

        $this->assertFalse($result);
    }

    public function testResolveSetVersionForUpdateKeepsCurrentWhenNewVersionIsLower(): void
    {
        $result = $this->helper->resolveSetVersionForUpdate('01.09', '01.10');

        $this->assertSame('01.10', $result);
    }

    public function testResolveSetVersionForUpdateAllowsEqualOrHigherVersion(): void
    {
        $this->assertSame('01.10', $this->helper->resolveSetVersionForUpdate('01.10', '01.10'));
        $this->assertSame('01.11', $this->helper->resolveSetVersionForUpdate('01.11', '01.10'));
    }

    public function testResolveSetVersionForUpdateUsesNewVersionWhenCurrentMissing(): void
    {
        $result = $this->helper->resolveSetVersionForUpdate('01.05', null);

        $this->assertSame('01.05', $result);
    }

    public function testIsIncomingSetVersionOlderThanStoredReturnsTrueForLowerVersion(): void
    {
        $result = $this->helper->isIncomingSetVersionOlderThanStored('01.09', '01.10');

        $this->assertTrue($result);
    }

    public function testIsIncomingSetVersionOlderThanStoredReturnsFalseForEqualOrHigherVersion(): void
    {
        $this->assertFalse($this->helper->isIncomingSetVersionOlderThanStored('01.10', '01.10'));
        $this->assertFalse($this->helper->isIncomingSetVersionOlderThanStored('01.11', '01.10'));
    }
}
