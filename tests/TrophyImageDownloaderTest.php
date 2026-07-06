<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/ImageHashCalculatorTest.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyImageDirectories.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyImageDownloader.php';

final class TrophyImageDownloaderTest extends TestCase
{
    private string $tempDirectory;

    protected function setUp(): void
    {
        $this->tempDirectory = sys_get_temp_dir() . '/psn100-image-downloader-' . uniqid('', true) . '/';
        mkdir($this->tempDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDirectory);
    }

    public function testBuildFilenameUsesHashCalculatorAndPreservesExtension(): void
    {
        $downloader = $this->createDownloader(static fn (): ?string => null);

        $filename = $downloader->buildFilename('https://example.test/icon.PNG', 'image-data');

        $this->assertSame(md5('image-data') . '.png', $filename);
    }

    public function testDownloadMandatoryForScanStoresFileAndReturnsFilename(): void
    {
        $downloader = $this->createDownloader(static fn (string $url): ?string => $url === 'https://example.test/title.png'
            ? 'title-bytes'
            : null);
        $messages = [];

        $downloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
            static fn (string $url): ?string => $url === 'https://example.test/title.png' ? 'title-bytes' : null,
        );

        $filename = $downloader->downloadMandatoryForScan(
            'https://example.test/title.png',
            $this->tempDirectory,
            'title icon',
        );

        $this->assertSame(md5('title-bytes') . '.png', $filename);
        $this->assertTrue(file_exists($this->tempDirectory . $filename));
        $this->assertSame([], $messages);
    }

    public function testDownloadMandatoryForScanReturnsPlaceholderWhenFetchFails(): void
    {
        $messages = [];
        $downloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
            static fn (string $url): ?string => null,
        );

        $filename = $downloader->downloadMandatoryForScan(
            'https://example.test/title.png',
            $this->tempDirectory,
            'title icon',
        );

        $this->assertSame(TrophyImageDownloader::PLACEHOLDER_FILENAME, $filename);
        $this->assertSame(
            ['Unable to download title icon from "https://example.test/title.png".'],
            $messages
        );
    }

    public function testDownloadMandatoryForScanSkipsLoggingWhenPlaceholderAlreadyCached(): void
    {
        $messages = [];
        $downloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
            static fn (string $url): ?string => null,
        );

        $filename = $downloader->downloadMandatoryForScan(
            'https://example.test/title.png',
            $this->tempDirectory,
            'title icon',
            TrophyImageDownloader::PLACEHOLDER_FILENAME,
        );

        $this->assertSame(TrophyImageDownloader::PLACEHOLDER_FILENAME, $filename);
        $this->assertSame([], $messages);
    }

    public function testDownloadOptionalForScanReturnsNullForEmptyUrl(): void
    {
        $downloader = $this->createDownloader(static fn (string $url): ?string => 'bytes');

        $this->assertSame(null, $downloader->downloadOptionalForScan('', $this->tempDirectory, 'reward image'));
        $this->assertSame(null, $downloader->downloadOptionalForScan(null, $this->tempDirectory, 'reward image'));
    }

    public function testDownloadMandatoryForRescanReusesExistingFilenameWhenFetchFails(): void
    {
        $messages = [];
        $downloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
            static fn (string $url): ?string => null,
        );

        $filename = $downloader->downloadMandatoryForRescan(
            'https://example.test/title.png',
            $this->tempDirectory,
            'cached-title.png',
        );

        $this->assertSame('cached-title.png', $filename);
        $this->assertSame(
            ['Reusing cached image "cached-title.png" because "https://example.test/title.png" is unavailable.'],
            $messages
        );
    }

    public function testDownloadMandatoryForRescanThrowsWhenFetchFailsWithoutCache(): void
    {
        $downloader = $this->createDownloader(static fn (string $url): ?string => null);

        try {
            $downloader->downloadMandatoryForRescan(
                'https://example.test/title.png',
                $this->tempDirectory,
            );
            $this->fail('Expected RuntimeException when mandatory rescan download fails without cache.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Unable to download image from "https://example.test/title.png".',
                $exception->getMessage()
            );
        }
    }

    public function testDownloadOptionalForRescanReturnsExistingFilenameForEmptyUrl(): void
    {
        $downloader = $this->createDownloader(static fn (string $url): ?string => 'bytes');

        $this->assertSame(
            'cached-reward.png',
            $downloader->downloadOptionalForRescan(null, $this->tempDirectory, 'cached-reward.png')
        );
    }

    public function testDownloadOptionalForRescanKeepsCachedFilenameWhenFetchFails(): void
    {
        $messages = [];
        $downloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            static function (string $message) use (&$messages): void {
                $messages[] = $message;
            },
            static fn (string $url): ?string => null,
        );

        $filename = $downloader->downloadOptionalForRescan(
            'https://example.test/reward.png',
            $this->tempDirectory,
            'cached-reward.png',
        );

        $this->assertSame('cached-reward.png', $filename);
        $this->assertSame(
            ['Keeping cached optional image "cached-reward.png" because "https://example.test/reward.png" is unavailable.'],
            $messages
        );
    }

    public function testWithLoggerPreservesRemoteFileFetcher(): void
    {
        $downloader = new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            null,
            static fn (string $url): ?string => 'image-bytes',
        );

        $withLogger = $downloader->withLogger(static function (string $message): void {
        });

        $filename = $withLogger->downloadMandatoryForScan(
            'https://example.test/title.png',
            $this->tempDirectory,
            'title icon',
        );

        $this->assertSame(md5('image-bytes') . '.png', $filename);
    }

    public function testProductionDefaultDirectoriesExposeExpectedPaths(): void
    {
        $directories = TrophyImageDirectories::productionDefault();

        $this->assertSame('/home/psn100/public_html/img/title/', $directories->title);
        $this->assertSame('/home/psn100/public_html/img/group/', $directories->group);
        $this->assertSame('/home/psn100/public_html/img/trophy/', $directories->trophy);
        $this->assertSame('/home/psn100/public_html/img/reward/', $directories->reward);
    }

    private function createDownloader(?\Closure $remoteFileFetcher): TrophyImageDownloader
    {
        return new TrophyImageDownloader(
            new ImageHashCalculator(new FakeImageProcessor(supported: false)),
            null,
            $remoteFileFetcher,
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . $entry;
            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
