<?php

declare(strict_types=1);

class PsnpPlusClient
{
    private const DATA_URL = 'https://psnp-plus.huskycode.dev/list.json';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private static ?array $cachedList = null;

    private ?string $cacheFilePath = null;

    private static bool $configurationLoaded = false;

    private static ?string $configuredCachePath = null;

    public static function clearCachedList(): void
    {
        self::$cachedList = null;
        self::$configurationLoaded = false;
        self::$configuredCachePath = null;
    }

    public function __construct(?string $cacheFilePath = null)
    {
        $this->cacheFilePath = $this->normalizeCachePath(
            $cacheFilePath
            ?? ($_ENV['PSNP_PLUS_CACHE_PATH'] ?? null)
            ?? $this->loadConfiguredCachePath()
        );
    }

    /**
     * @return array<int, int[]>
     */
    public function getTrophiesByPsnprofilesId(): array
    {
        $list = $this->fetchList();

        $trophiesById = [];
        foreach ($list as $psnprofilesId => $entry) {
            $trophies = $entry['trophies'] ?? [];
            if (!is_array($trophies)) {
                $trophies = [];
            }

            $trophiesById[$psnprofilesId] = array_map('intval', $trophies);
        }

        return $trophiesById;
    }

    public function getNote(int $psnprofilesId): ?string
    {
        $list = $this->fetchList();
        if (!array_key_exists($psnprofilesId, $list)) {
            return null;
        }

        $note = $list[$psnprofilesId]['note'] ?? null;
        if (!is_string($note)) {
            return null;
        }

        $trimmed = trim($note);

        return $trimmed === '' ? null : $trimmed;
    }

    public function getCacheFilePath(): ?string
    {
        return $this->cacheFilePath;
    }

    public function downloadToPath(string $path): void
    {
        $json = $this->downloadListJson();
        $list = $this->decodeList($json);

        $payload = json_encode(['list' => $list], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new RuntimeException('Unable to encode PSNP+ cache data.');
        }

        $this->writeFileAtomically($path, $payload);

        self::$cachedList = $list;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchList(): array
    {
        if (self::$cachedList !== null) {
            return self::$cachedList;
        }

        $cachedList = $this->loadCachedList();
        if ($cachedList !== null) {
            self::$cachedList = $cachedList;

            return self::$cachedList;
        }

        $list = $this->downloadList();
        self::$cachedList = $list;

        return self::$cachedList;
    }

    private function normalizeCachePath(mixed $cacheFilePath): ?string
    {
        if (!is_string($cacheFilePath)) {
            return null;
        }

        $trimmed = trim($cacheFilePath);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadCachedList(): ?array
    {
        if ($this->cacheFilePath === null || !is_file($this->cacheFilePath)) {
            return null;
        }

        $json = @file_get_contents($this->cacheFilePath);
        if ($json === false) {
            return null;
        }

        try {
            return $this->decodeList($json);
        } catch (RuntimeException $exception) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function downloadList(): array
    {
        $json = $this->downloadListJson();

        return $this->decodeList($json);
    }

    protected function downloadListJson(): string
    {
        $json = @file_get_contents(self::DATA_URL);

        if ($json === false) {
            throw new RuntimeException('Unable to download PSNP+ data.');
        }

        return $json;
    }

    private function loadConfiguredCachePath(): ?string
    {
        if (self::$configurationLoaded) {
            return self::$configuredCachePath;
        }

        self::$configurationLoaded = true;

        $configurationFile = __DIR__ . '/../config/psnp-plus.php';
        if (!is_file($configurationFile)) {
            return null;
        }

        $configuration = @include $configurationFile;
        if (!is_array($configuration)) {
            return null;
        }

        $cachePath = $configuration['cache_path'] ?? null;
        if (is_string($cachePath)) {
            self::$configuredCachePath = $this->normalizeCachePath($cachePath);
        }

        return self::$configuredCachePath;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeList(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['list']) || !is_array($decoded['list'])) {
            throw new RuntimeException('Invalid PSNP+ data received.');
        }

        $normalized = [];
        foreach ($decoded['list'] as $psnprofilesId => $entry) {
            if (!is_array($entry)) {
                $entry = [];
            }

            $normalized[(int) $psnprofilesId] = $entry;
        }

        return $normalized;
    }

    private function writeFileAtomically(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new RuntimeException('PSNP+ cache directory does not exist.');
        }

        $temporaryPath = tempnam($directory, 'psnp-plus-cache-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create PSNP+ cache file.');
        }

        $bytesWritten = @file_put_contents($temporaryPath, $contents);
        if ($bytesWritten === false) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to write PSNP+ cache file.');
        }

        if ($bytesWritten !== strlen($contents)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Failed to write the full PSNP+ cache file.');
        }

        if (@chmod($temporaryPath, 0644) === false) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to set permissions on PSNP+ cache file.');
        }

        if (@rename($temporaryPath, $path) === false) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to replace PSNP+ cache file.');
        }
    }
}
