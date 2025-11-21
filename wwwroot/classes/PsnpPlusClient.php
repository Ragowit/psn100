<?php

declare(strict_types=1);

class PsnpPlusClient
{
    private const DATA_URL = 'https://psnp-plus.huskycode.dev/list.json';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private static ?array $cachedList = null;

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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchList(): array
    {
        if (self::$cachedList !== null) {
            return self::$cachedList;
        }

        $json = @file_get_contents(self::DATA_URL);

        if ($json === false) {
            throw new RuntimeException('Unable to download PSNP+ data.');
        }

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

        self::$cachedList = $normalized;

        return self::$cachedList;
    }
}
