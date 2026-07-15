<?php

declare(strict_types=1);

require_once __DIR__ . '/ImageHashCalculator.php';

/**
 * Downloads PSN trophy artwork and stores hashed filenames on disk.
 *
 * Encapsulates the remote fetch, filename hashing, and persistence rules that
 * were previously duplicated in ThirtyMinuteCronJob and GameRescanService.
 */
final readonly class TrophyImageDownloader
{
    public const string PLACEHOLDER_FILENAME = '.png';

    public function __construct(
        private readonly ImageHashCalculator $imageHashCalculator,
        private readonly ?\Closure $logger = null,
        private readonly ?\Closure $remoteFileFetcher = null,
    ) {
    }

    public function withLogger(?\Closure $logger): self
    {
        return clone($this, ['logger' => $logger]);
    }

    /**
     * Player-scan flow: log download failures when no usable cache exists and
     * fall back to the shared placeholder filename.
     */
    public function downloadMandatoryForScan(
        string $url,
        string $directory,
        string $description,
        ?string $existingFilename = null,
    ): string {
        $contents = $this->fetchRemoteFile($url);
        if ($contents === null) {
            if ($this->shouldLogDownloadFailure($existingFilename)) {
                $this->log(sprintf('Unable to download %s from "%s".', $description, $url));
            }

            return self::PLACEHOLDER_FILENAME;
        }

        $storedFilename = $this->storeImageContentsForScan($url, $directory, $description, $contents);

        return $storedFilename ?? self::PLACEHOLDER_FILENAME;
    }

    /**
     * Player-scan flow for optional reward images.
     */
    public function downloadOptionalForScan(
        ?string $url,
        string $directory,
        string $description,
        ?string $existingFilename = null,
    ): ?string {
        if ($url === null || $url === '') {
            return null;
        }

        $contents = $this->fetchRemoteFile($url);
        if ($contents === null) {
            if ($this->shouldLogDownloadFailure($existingFilename)) {
                $this->log(sprintf('Unable to download %s from "%s".', $description, $url));
            }

            return self::PLACEHOLDER_FILENAME;
        }

        $storedFilename = $this->storeImageContentsForScan($url, $directory, $description, $contents);

        return $storedFilename ?? self::PLACEHOLDER_FILENAME;
    }

    /**
     * Admin rescan flow: reuse an existing cached filename when the remote file
     * is unavailable, otherwise throw so the rescan can abort visibly.
     */
    public function downloadMandatoryForRescan(
        string $url,
        string $directory,
        ?string $existingFilename = null,
    ): string {
        $contents = $this->fetchRemoteFile($url);
        if ($contents === null) {
            if ($existingFilename !== null && $existingFilename !== '') {
                $this->log(
                    sprintf('Reusing cached image "%s" because "%s" is unavailable.', $existingFilename, $url)
                );

                return $existingFilename;
            }

            $this->log(sprintf('Unable to download image from "%s".', $url));

            throw new RuntimeException(sprintf('Unable to download image from "%s".', $url));
        }

        return $this->storeImageContentsForRescan($url, $directory, $contents);
    }

    /**
     * Admin rescan flow for optional reward images.
     */
    public function downloadOptionalForRescan(
        ?string $url,
        string $directory,
        ?string $existingFilename = null,
    ): ?string {
        if ($url === null || $url === '') {
            return $existingFilename;
        }

        $contents = $this->fetchRemoteFile($url);
        if ($contents === null) {
            if ($existingFilename !== null && $existingFilename !== '') {
                $this->log(
                    sprintf(
                        'Keeping cached optional image "%s" because "%s" is unavailable.',
                        $existingFilename,
                        $url
                    )
                );
            }

            return $existingFilename;
        }

        return $this->storeImageContentsForRescan($url, $directory, $contents);
    }

    public function fetchRemoteFile(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if ($this->remoteFileFetcher !== null) {
            return ($this->remoteFileFetcher)($url);
        }

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'ignore_errors' => true,
                ],
            ]);

            $contents = @file_get_contents($url, false, $context);
            if ($contents !== false) {
                $statusLine = array_first($http_response_header) ?? '';
                if ($statusLine === '' || preg_match('/^HTTP\/\S+\s+2\d\d\b/', $statusLine)) {
                    return $contents;
                }
            }

            if ($attempt === 1) {
                sleep(3);
            }
        }

        return null;
    }

    public function buildFilename(string $url, string $contents): string
    {
        $hash = $this->imageHashCalculator->calculate($contents);
        if ($hash === null) {
            $hash = md5($contents);
        }
        $extensionPosition = strrpos($url, '.');
        $extension = $extensionPosition === false ? '' : strtolower(substr($url, $extensionPosition));

        return $hash . $extension;
    }

    private function shouldLogDownloadFailure(?string $existingFilename): bool
    {
        if ($existingFilename === null || $existingFilename === '') {
            return true;
        }

        return $existingFilename !== self::PLACEHOLDER_FILENAME;
    }

    private function storeImageContentsForScan(
        string $url,
        string $directory,
        string $description,
        string $contents,
    ): ?string {
        $filename = $this->buildFilename($url, $contents);
        $path = $directory . $filename;

        if (!file_exists($path)) {
            if (@file_put_contents($path, $contents) === false) {
                $this->log(sprintf('Unable to save %s from "%s" to "%s".', $description, $url, $path));

                return null;
            }
        }

        return $filename;
    }

    private function storeImageContentsForRescan(string $url, string $directory, string $contents): string
    {
        $filename = $this->buildFilename($url, $contents);
        $path = $directory . $filename;

        if (!file_exists($path)) {
            file_put_contents($path, $contents);
        }

        return $filename;
    }

    private function log(string $message): void
    {
        if ($this->logger === null) {
            return;
        }

        ($this->logger)($message);
    }
}
