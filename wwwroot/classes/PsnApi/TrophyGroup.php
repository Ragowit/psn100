<?php

declare(strict_types=1);

namespace PsnApi;

final class TrophyGroup extends AbstractResource
{
    private string $npCommunicationId;

    private string $serviceName;

    private ?string $accountId;

    private string $groupId;

    private string $groupName;

    private string $groupIconUrl;

    private string $groupDetail;

    public function __construct(
        HttpClient $httpClient,
        string $npCommunicationId,
        string $serviceName,
        ?string $accountId,
        string $groupId,
        string $groupName = '',
        string $groupIconUrl = '',
        string $groupDetail = '',
        ?object $data = null
    ) {
        parent::__construct($httpClient, $data);
        $this->npCommunicationId = $npCommunicationId;
        $this->serviceName = $serviceName;
        $this->accountId = $accountId;
        $this->groupId = $groupId;
        $this->groupName = $groupName;
        $this->groupIconUrl = $groupIconUrl;
        $this->groupDetail = $groupDetail;
    }

    public static function forTitle(
        HttpClient $httpClient,
        ?string $accountId,
        string $npCommunicationId,
        string $serviceName
    ): array {
        $query = ['npServiceName' => $serviceName];

        if ($accountId !== null) {
            $response = $httpClient->get(
                'trophy/v1/users/' . $accountId . '/npCommunicationIds/' . $npCommunicationId . '/trophyGroups',
                $query
            )->getJson();
        } else {
            $response = $httpClient->get(
                'trophy/v1/npCommunicationIds/' . $npCommunicationId . '/trophyGroups',
                $query
            )->getJson();
        }

        if (!is_object($response) || !isset($response->trophyGroups) || !is_array($response->trophyGroups)) {
            return [];
        }

        $groups = [];
        foreach ($response->trophyGroups as $group) {
            if (!is_object($group)) {
                continue;
            }

            $groups[] = new self(
                $httpClient,
                $npCommunicationId,
                $serviceName,
                $accountId,
                (string) ($group->trophyGroupId ?? ''),
                (string) ($group->trophyGroupName ?? ''),
                (string) ($group->trophyGroupIconUrl ?? ''),
                (string) ($group->trophyGroupDetail ?? ''),
                $group
            );
        }

        return $groups;
    }

    public function id(): string
    {
        return $this->groupId;
    }

    public function name(): string
    {
        return $this->groupName !== '' ? $this->groupName : (string) ($this->pluck('trophyGroupName') ?? '');
    }

    public function detail(): string
    {
        return $this->groupDetail !== '' ? $this->groupDetail : (string) ($this->pluck('trophyGroupDetail') ?? '');
    }

    public function iconUrl(): string
    {
        return $this->groupIconUrl !== '' ? $this->groupIconUrl : (string) ($this->pluck('trophyGroupIconUrl') ?? '');
    }

    /**
     * @return Trophy[]
     */
    public function trophies(): array
    {
        return Trophy::forGroup(
            $this->httpClient,
            $this->accountId,
            $this->npCommunicationId,
            $this->serviceName,
            $this->groupId
        );
    }

    protected function fetch(): object
    {
        return $this->httpClient
            ->get(
                'trophy/v1/npCommunicationIds/' . $this->npCommunicationId . '/trophyGroups/' . $this->groupId,
                ['npServiceName' => $this->serviceName]
            )
            ->getJson();
    }
}
