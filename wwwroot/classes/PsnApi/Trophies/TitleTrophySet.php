<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Trophies;

use Achievements\PsnApi\Client;
use Achievements\PsnApi\Exceptions\ApiException;

final class TitleTrophySet
{
    private Client $client;

    private string $npCommunicationId;

    private string $serviceName;

    /** @var list<TitleTrophyGroup>|null */
    private ?array $groups = null;

    public function __construct(Client $client, string $npCommunicationId, string $serviceName)
    {
        $this->client = $client;
        $this->npCommunicationId = $npCommunicationId;
        $this->serviceName = $serviceName;
    }

    /**
     * @return list<TitleTrophyGroup>
     */
    public function trophyGroups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $authorizationHeaders = $this->client->authorizationHeaders();

        $groupResponse = $this->client->requestJson(
            'GET',
            sprintf(
                'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups?npServiceName=%s',
                rawurlencode($this->npCommunicationId),
                rawurlencode($this->serviceName)
            ),
            null,
            $authorizationHeaders
        );

        $trophyResponse = $this->client->requestJson(
            'GET',
            sprintf(
                'https://m.np.playstation.com/api/trophy/v1/npCommunicationIds/%s/trophyGroups/all/trophies?npServiceName=%s',
                rawurlencode($this->npCommunicationId),
                rawurlencode($this->serviceName)
            ),
            null,
            $authorizationHeaders
        );

        if (!isset($groupResponse['trophyGroups']) || !is_array($groupResponse['trophyGroups'])) {
            throw new ApiException('Unexpected response when retrieving trophy groups.', 0, $groupResponse);
        }

        if (!isset($trophyResponse['trophies']) || !is_array($trophyResponse['trophies'])) {
            throw new ApiException('Unexpected response when retrieving trophies.', 0, $trophyResponse);
        }

        /** @var array<int, array<string, mixed>> $groupData */
        $groupData = $groupResponse['trophyGroups'];

        /** @var array<int, array<string, mixed>> $trophyData */
        $trophyData = $trophyResponse['trophies'];

        $trophiesByGroup = [];
        foreach ($trophyData as $trophy) {
            $groupId = (string) ($trophy['trophyGroupId'] ?? 'default');
            $trophiesByGroup[$groupId][] = new TitleTrophy($trophy);
        }

        $groups = [];
        foreach ($groupData as $group) {
            $groupId = (string) ($group['trophyGroupId'] ?? 'default');
            $groups[] = new TitleTrophyGroup(
                $groupId,
                (string) ($group['trophyGroupName'] ?? ''),
                (string) ($group['trophyGroupIconUrl'] ?? ''),
                isset($group['trophyGroupDetail']) ? (string) $group['trophyGroupDetail'] : null,
                $trophiesByGroup[$groupId] ?? []
            );
        }

        return $this->groups = $groups;
    }
}
