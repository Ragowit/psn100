<?php

declare(strict_types=1);

namespace PsnApi;

final class Trophy extends AbstractResource
{
    private string $npCommunicationId;

    private string $serviceName;

    private ?string $accountId;

    private string $groupId;

    private int $trophyId;

    public function __construct(
        HttpClient $httpClient,
        ?string $accountId,
        string $npCommunicationId,
        string $serviceName,
        string $groupId,
        int $trophyId,
        ?object $data = null
    ) {
        parent::__construct($httpClient, $data);
        $this->accountId = $accountId;
        $this->npCommunicationId = $npCommunicationId;
        $this->serviceName = $serviceName;
        $this->groupId = $groupId;
        $this->trophyId = $trophyId;
    }

    /**
     * @return list<Trophy>
     */
    public static function forGroup(
        HttpClient $httpClient,
        ?string $accountId,
        string $npCommunicationId,
        string $serviceName,
        string $groupId
    ): array {
        $query = ['npServiceName' => $serviceName];

        if ($accountId !== null) {
            $response = $httpClient->get(
                'trophy/v1/users/' . $accountId . '/npCommunicationIds/' . $npCommunicationId . '/trophyGroups/' . $groupId . '/trophies',
                $query
            )->getJson();
        } else {
            $response = $httpClient->get(
                'trophy/v1/npCommunicationIds/' . $npCommunicationId . '/trophyGroups/' . $groupId . '/trophies',
                $query
            )->getJson();
        }

        if (!is_object($response) || !isset($response->trophies) || !is_array($response->trophies)) {
            return [];
        }

        $trophies = [];
        foreach ($response->trophies as $trophyData) {
            if (!is_object($trophyData)) {
                continue;
            }

            $trophyId = (int) ($trophyData->trophyId ?? 0);
            $trophies[] = new self(
                $httpClient,
                $accountId,
                $npCommunicationId,
                $serviceName,
                $groupId,
                $trophyId,
                $trophyData
            );
        }

        return $trophies;
    }

    public function id(): int
    {
        return $this->trophyId;
    }

    public function hidden(): bool
    {
        return (bool) ($this->pluck('trophyHidden') ?? false);
    }

    public function type(): TrophyType
    {
        return new TrophyType((string) ($this->pluck('trophyType') ?? ''));
    }

    public function name(): string
    {
        return (string) ($this->pluck('trophyName') ?? '');
    }

    public function detail(): string
    {
        return (string) ($this->pluck('trophyDetail') ?? '');
    }

    public function iconUrl(): string
    {
        return (string) ($this->pluck('trophyIconUrl') ?? '');
    }

    public function progressTargetValue(): string
    {
        return (string) ($this->pluck('trophyProgressTargetValue') ?? '');
    }

    public function rewardName(): string
    {
        return (string) ($this->pluck('trophyRewardName') ?? '');
    }

    public function rewardImageUrl(): string
    {
        return (string) ($this->pluck('trophyRewardImageUrl') ?? '');
    }

    protected function fetch(): object
    {
        return $this->httpClient
            ->get(
                'trophy/v1/npCommunicationIds/' . $this->npCommunicationId . '/trophyGroups/' . $this->groupId . '/trophies/' . $this->trophyId,
                ['npServiceName' => $this->serviceName]
            )
            ->getJson();
    }
}
