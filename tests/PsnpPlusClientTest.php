<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PsnpPlusClient.php';

final class PsnpPlusClientTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        PsnpPlusClient::clearCachedList();

        $this->temporaryDirectory = sys_get_temp_dir() . '/psnp-plus-client-' . uniqid('', true);
        if (!is_dir($this->temporaryDirectory)) {
            mkdir($this->temporaryDirectory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->temporaryDirectory)) {
            return;
        }

        $files = glob($this->temporaryDirectory . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        rmdir($this->temporaryDirectory);
    }

    public function testFetchListPrefersCachedFile(): void
    {
        $cacheFile = $this->temporaryDirectory . '/cache.json';
        file_put_contents($cacheFile, json_encode(['list' => [123 => ['trophies' => [1, 2, 3]]]], JSON_THROW_ON_ERROR));

        $client = new class ($cacheFile) extends PsnpPlusClient {
            public int $downloadCalls = 0;

            protected function downloadListJson(): string
            {
                $this->downloadCalls++;

                return json_encode(['list' => [456 => ['trophies' => [9]]]], JSON_THROW_ON_ERROR);
            }
        };

        $result = $client->getTrophiesByPsnprofilesId();

        $this->assertSame([123 => [1, 2, 3]], $result);
        $this->assertSame(0, $client->downloadCalls);
    }

    public function testDownloadToPathWritesNormalizedCache(): void
    {
        $cacheFile = $this->temporaryDirectory . '/cache.json';

        $client = new class ($cacheFile) extends PsnpPlusClient {
            protected function downloadListJson(): string
            {
                return json_encode(['list' => ['789' => ['trophies' => [5], 'note' => 'Test']]], JSON_THROW_ON_ERROR);
            }
        };

        $client->downloadToPath($cacheFile);

        $contents = file_get_contents($cacheFile);
        $this->assertTrue($contents !== false, 'Cache file should be readable.');

        $decoded = json_decode($contents === false ? '' : $contents, true);
        $this->assertSame(['list' => [789 => ['trophies' => [5], 'note' => 'Test']]], $decoded);

        $this->assertSame([789 => [5]], $client->getTrophiesByPsnprofilesId());
    }
}
