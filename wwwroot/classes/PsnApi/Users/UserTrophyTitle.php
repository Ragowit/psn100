<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

use Achievements\PsnApi\Client;
use Achievements\PsnApi\Exceptions\ApiException;
use Achievements\PsnApi\Exceptions\NotFoundException;
use Achievements\PsnApi\Trophies\TitleTrophy;
use Achievements\PsnApi\Trophies\TitleTrophyGroup;

final class UserTrophyTitle
{
    private Client $client;

    private string $accountId;

    /** @var array<string, mixed> */
    private array $data;

    /** @var list<UserTrophyGroup>|null */
    private ?array $groups = null;

    public function __construct(Client $client, string $accountId, array $data)
    {
        $this->client = $client;
        $this->accountId = $accountId;
        $this->data = $data;
    }

    public function npCommunicationId(): string
    {
        return (string) ($this->data['npCommunicationId'] ?? '');
    }

    public function serviceName(): string
    {
        return (string) ($this->data['npServiceName'] ?? 'trophy');
    }

    public function trophySetVersion(): string
    {
        return (string) ($this->data['trophySetVersion'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->data['trophyTitleName'] ?? '');
    }

    public function detail(): string
    {
        return (string) ($this->data['trophyTitleDetail'] ?? '');
    }

    public function iconUrl(): string
    {
        return (string) ($this->data['trophyTitleIconUrl'] ?? '');
    }

    public function lastUpdatedDateTime(): string
    {
        return (string) ($this->data['lastUpdatedDateTime'] ?? '');
    }

    /**
     * @return list<UserTrophyTitlePlatform>
     */
    public function platform(): array
    {
        $platform = (string) ($this->data['trophyTitlePlatform'] ?? '');

        if ($platform === '') {
            return [];
        }

        $values = array_map('trim', explode(',', $platform));

        $platforms = [];
        foreach ($values as $value) {
            $platforms[] = new UserTrophyTitlePlatform($value);
        }

        return $platforms;
    }

    /**
     * @return list<UserTrophyGroup>
     */
    public function trophyGroups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $authorizationHeaders = $this->client->authorizationHeaders();
        $npCommunicationId = $this->npCommunicationId();
        $serviceName = $this->serviceName();

        try {
            $userTrophiesResponse = $this->client->requestJson(
                'GET',
                sprintf(
                    'https://m.np.playstation.com/api/trophy/v1/users/%s/npCommunicationIds/%s/trophyGroups/all/trophies?npServiceName=%s',
                    rawurlencode($this->accountId),
                    rawurlencode($npCommunicationId),
                    rawurlencode($serviceName)
                ),
                null,
                $authorizationHeaders
            );
        } catch (ApiException $exception) {
            $statusCode = $exception->getCode();

            if ($statusCode === 403 || $statusCode === 404) {
                throw new NotFoundException('Unable to retrieve user trophies for the specified title.', 0, $exception);
            }

            throw $exception;
        }

        if (!isset($userTrophiesResponse['trophies']) || !is_array($userTrophiesResponse['trophies'])) {
            throw new ApiException('Unexpected response when retrieving user trophies.', 0, $userTrophiesResponse);
        }

        /** @var array<int, array<string, mixed>> $userTrophyData */
        $userTrophyData = $userTrophiesResponse['trophies'];

        $trophyMetadataSet = $this->client->trophies($npCommunicationId, $serviceName);
        $metadataGroups = $trophyMetadataSet->trophyGroups();

        $metadataById = [];
        foreach ($metadataGroups as $group) {
            foreach ($group->trophies() as $trophy) {
                $metadataById[$group->id()][$trophy->id()] = $trophy;
            }
        }

        $trophiesByGroup = [];
        foreach ($userTrophyData as $userTrophy) {
            $groupId = (string) ($userTrophy['trophyGroupId'] ?? 'default');
            $trophyId = (int) ($userTrophy['trophyId'] ?? 0);

            if (!isset($metadataById[$groupId][$trophyId])) {
                continue;
            }

            $trophiesByGroup[$groupId][] = new UserTrophy($metadataById[$groupId][$trophyId], $userTrophy);
        }

        $groups = [];
        foreach ($metadataGroups as $group) {
            $groups[] = new UserTrophyGroup(
                $group->id(),
                $group->name(),
                $group->iconUrl(),
                $group->detail(),
                $trophiesByGroup[$group->id()] ?? []
            );
        }

        return $this->groups = $groups;
    }
}
