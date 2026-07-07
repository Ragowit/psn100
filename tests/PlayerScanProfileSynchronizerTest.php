<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Psn100Logger.php';
require_once __DIR__ . '/../wwwroot/classes/ImageHashCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/WorkerScanCoordinator.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerAvatarSynchronizer.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerScanProfileSynchronizer.php';

final class PlayerScanProfileSynchronizerTest extends TestCase
{
    private PlayerScanProfileSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE log (message TEXT NOT NULL)');
        $database->exec('CREATE TABLE setting (id INTEGER PRIMARY KEY, scanning TEXT, scan_progress TEXT)');
        $database->exec('CREATE TABLE player_queue (online_id TEXT PRIMARY KEY)');
        $database->exec(
            'CREATE TABLE player (
                account_id INTEGER PRIMARY KEY,
                online_id TEXT NOT NULL,
                country TEXT,
                status INTEGER NOT NULL DEFAULT 99,
                last_updated_date TEXT
            )'
        );

        $logger = new Psn100Logger($database);

        $this->synchronizer = new PlayerScanProfileSynchronizer(
            $database,
            $logger,
            new WorkerScanCoordinator($database),
        );
    }

    public function testDetermineResolvedOnlineIdPrefersCurrentOnlineId(): void
    {
        $profile = [
            'currentOnlineId' => 'NewName',
            'onlineId' => 'OldName',
        ];

        $result = $this->synchronizer->determineResolvedOnlineId($profile, 'FallbackName');

        $this->assertSame('NewName', $result);
    }

    public function testDetermineResolvedOnlineIdFallsBackToOnlineId(): void
    {
        $profile = [
            'onlineId' => 'StoredName',
        ];

        $result = $this->synchronizer->determineResolvedOnlineId($profile, 'FallbackName');

        $this->assertSame('StoredName', $result);
    }

    public function testDetermineResolvedOnlineIdUsesFallbackWhenProfileIdsMissing(): void
    {
        $result = $this->synchronizer->determineResolvedOnlineId([], 'FallbackName');

        $this->assertSame('FallbackName', $result);
    }

    public function testExtractCountryFromNpIdDecodesTrailingCountryCode(): void
    {
        $npId = base64_encode('ExamplePlayerUS');

        $result = $this->synchronizer->extractCountryFromNpId($npId);

        $this->assertSame('us', $result);
    }

    public function testExtractCountryFromNpIdReturnsNullForInvalidValues(): void
    {
        $this->assertSame(null, $this->synchronizer->extractCountryFromNpId(''));
        $this->assertSame(null, $this->synchronizer->extractCountryFromNpId(null));
        $this->assertSame(null, $this->synchronizer->extractCountryFromNpId('not-valid-base64!!!'));
    }

    public function testNormalizeAccountIdValueAcceptsIntegersAndDigitStrings(): void
    {
        $this->assertSame('12345', $this->synchronizer->normalizeAccountIdValue(12345));
        $this->assertSame('12345', $this->synchronizer->normalizeAccountIdValue(' 12345 '));
    }

    public function testNormalizeAccountIdValueRejectsNonNumericValues(): void
    {
        $this->assertSame(null, $this->synchronizer->normalizeAccountIdValue(''));
        $this->assertSame(null, $this->synchronizer->normalizeAccountIdValue('abc'));
        $this->assertSame(null, $this->synchronizer->normalizeAccountIdValue(null));
    }

    public function testSyncResultHelpers(): void
    {
        $success = PlayerScanProfileSyncResult::success(['online_id' => 'player'], new stdClass(), 'us');
        $skip = PlayerScanProfileSyncResult::skipPlayer();

        $this->assertTrue($success->isSuccess());
        $this->assertFalse($success->shouldSkipPlayer());
        $this->assertFalse($skip->isSuccess());
        $this->assertTrue($skip->shouldSkipPlayer());
    }
}
