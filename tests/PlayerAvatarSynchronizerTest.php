<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/ImageHashCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Cron/PlayerAvatarSynchronizer.php';

final class PlayerAvatarSynchronizerTest extends TestCase
{
    private PDO $database;

    private string $avatarStorageDirectory;

    private string $avatarSourcePath;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE psn100_avatars (
                avatar_id INTEGER PRIMARY KEY AUTOINCREMENT,
                size TEXT NOT NULL,
                avatar_url TEXT NOT NULL,
                md5_hash TEXT,
                extension TEXT NOT NULL
            )'
        );

        $this->avatarStorageDirectory = sys_get_temp_dir() . '/psn100-avatar-test-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->avatarStorageDirectory, 0777, true);

        $this->avatarSourcePath = sys_get_temp_dir() . '/psn100-avatar-source-' . bin2hex(random_bytes(4)) . '.png';
        file_put_contents(
            $this->avatarSourcePath,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true)
        );
    }

    public function testReturnsCachedFilenameWhenAvatarUrlAlreadyKnown(): void
    {
        $avatarUrl = 'https://example.test/avatar-xl.png';
        $query = $this->database->prepare(
            'INSERT INTO psn100_avatars(size, avatar_url, md5_hash, extension)
            VALUES(:size, :avatar_url, :md5_hash, :extension)'
        );
        $query->execute([
            ':size' => 'xl',
            ':avatar_url' => $avatarUrl,
            ':md5_hash' => 'cachedhash',
            ':extension' => 'png',
        ]);

        $synchronizer = new PlayerAvatarSynchronizer(
            $this->database,
            new ImageHashCalculator(),
            $this->avatarStorageDirectory,
        );

        $filename = $synchronizer->synchronizeFromPsnUser(new PlayerAvatarSynchronizerTestUser([
            'xl' => $avatarUrl,
            'l' => 'https://example.test/avatar-l.png',
            'm' => 'https://example.test/avatar-m.png',
            's' => 'https://example.test/avatar-s.png',
        ]));

        $this->assertSame('cachedhash.png', $filename);
    }

    public function testDownloadsAvatarAndPersistsCatalogRow(): void
    {
        $avatarUrl = 'file://' . $this->avatarSourcePath;
        $synchronizer = new PlayerAvatarSynchronizer(
            $this->database,
            new ImageHashCalculator(),
            $this->avatarStorageDirectory,
        );

        $filename = $synchronizer->synchronizeFromPsnUser(new PlayerAvatarSynchronizerTestUser([
            'xl' => $avatarUrl,
            'l' => 'file:///does-not-exist.png',
            'm' => 'file:///does-not-exist.png',
            's' => 'file:///does-not-exist.png',
        ]));

        $this->assertTrue($filename !== '');
        $this->assertTrue(file_exists($this->avatarStorageDirectory . $filename));

        $query = $this->database->prepare('SELECT size, avatar_url, extension FROM psn100_avatars');
        $query->execute();
        $row = $query->fetch(PDO::FETCH_ASSOC);

        $this->assertSame('xl', $row['size']);
        $this->assertSame($avatarUrl, $row['avatar_url']);
        $this->assertSame('png', $row['extension']);
    }

    public function testFallsBackToNextAvatarSizeWhenDownloadFails(): void
    {
        $largeAvatarUrl = 'file://' . $this->avatarSourcePath;
        $synchronizer = new PlayerAvatarSynchronizer(
            $this->database,
            new ImageHashCalculator(),
            $this->avatarStorageDirectory,
        );

        $filename = $synchronizer->synchronizeFromPsnUser(new PlayerAvatarSynchronizerTestUser([
            'xl' => 'file:///does-not-exist-xl.png',
            'l' => $largeAvatarUrl,
            'm' => 'file:///does-not-exist-m.png',
            's' => 'file:///does-not-exist-s.png',
        ]));

        $this->assertTrue($filename !== '');
        $this->assertTrue(file_exists($this->avatarStorageDirectory . $filename));

        $query = $this->database->prepare('SELECT size FROM psn100_avatars');
        $query->execute();

        $this->assertSame('l', $query->fetchColumn());
    }

    public function testReturnsEmptyStringWhenNoAvatarCanBeDownloaded(): void
    {
        $synchronizer = new PlayerAvatarSynchronizer(
            $this->database,
            new ImageHashCalculator(),
            $this->avatarStorageDirectory,
        );

        $filename = $synchronizer->synchronizeFromPsnUser(new PlayerAvatarSynchronizerTestUser([
            'xl' => 'file:///does-not-exist-xl.png',
            'l' => 'file:///does-not-exist-l.png',
            'm' => 'file:///does-not-exist-m.png',
            's' => 'file:///does-not-exist-s.png',
        ]));

        $this->assertSame('', $filename);
    }
}

/**
 * @param array<string, string> $avatarUrls
 */
final class PlayerAvatarSynchronizerTestUser
{
    public function __construct(private readonly array $avatarUrls)
    {
    }

    /**
     * @return array<string, string>
     */
    public function avatarUrls(): array
    {
        return $this->avatarUrls;
    }
}
