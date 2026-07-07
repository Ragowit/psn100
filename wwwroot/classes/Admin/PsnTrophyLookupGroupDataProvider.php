<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnGameLookupService.php';
require_once __DIR__ . '/PsnTrophyApiAdapter.php';
require_once __DIR__ . '/PsnTrophyGroupApiAdapter.php';

use Tustin\PlayStation\Client;

/**
 * Fetches PSN trophy group data and adapts raw API payloads to objects used during game rescans.
 */
final class PsnTrophyLookupGroupDataProvider
{
    public function __construct(
        private readonly PsnGameLookupService $psnGameLookupService,
    ) {
    }

    /**
     * @return array<int, array{group: PsnTrophyGroupApiAdapter, trophies: array<int, PsnTrophyApiAdapter>}>
     */
    public function fetchGroupData(Client $client, string $npCommunicationId): array
    {
        $trophyData = $this->psnGameLookupService->fetchTrophyDataForNpCommunicationId($npCommunicationId, $client);

        return self::adaptTrophyData($trophyData);
    }

    /**
     * @param array<string, mixed> $trophyData
     * @return array<int, array{group: PsnTrophyGroupApiAdapter, trophies: array<int, PsnTrophyApiAdapter>}>
     */
    public static function adaptTrophyData(array $trophyData): array
    {
        $rawGroups = $trophyData['trophyGroups'] ?? [];

        if (!is_array($rawGroups)) {
            return [];
        }

        $groupData = [];

        foreach ($rawGroups as $rawGroup) {
            if (!is_array($rawGroup)) {
                continue;
            }

            $groupId = (string) ($rawGroup['trophyGroupId'] ?? '');
            $rawTrophies = $rawGroup['trophies'] ?? [];

            if (!is_array($rawTrophies)) {
                $rawTrophies = [];
            }

            $trophies = [];
            foreach ($rawTrophies as $rawTrophy) {
                if (!is_array($rawTrophy)) {
                    continue;
                }

                $trophies[] = new PsnTrophyApiAdapter($rawTrophy);
            }

            $groupData[] = [
                'group' => new PsnTrophyGroupApiAdapter($groupId, $rawGroup),
                'trophies' => $trophies,
            ];
        }

        return $groupData;
    }
}
