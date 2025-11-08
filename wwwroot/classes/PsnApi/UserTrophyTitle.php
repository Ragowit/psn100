<?php

declare(strict_types=1);

namespace PsnApi;

final class UserTrophyTitle extends AbstractResource
{
    private string $accountId;

    private string $npCommunicationId;

    private string $serviceName;

    public function __construct(HttpClient $httpClient, string $accountId, object $data)
    {
        parent::__construct($httpClient, $data);
        $this->accountId = $accountId;
        $this->npCommunicationId = (string) ($data->npCommunicationId ?? '');
        $this->serviceName = (string) ($data->npServiceName ?? 'trophy');
    }

    public function npCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function name(): string
    {
        return (string) ($this->pluck('trophyTitleName') ?? '');
    }

    public function detail(): string
    {
        return (string) ($this->pluck('trophyTitleDetail') ?? '');
    }

    public function iconUrl(): string
    {
        return (string) ($this->pluck('trophyTitleIconUrl') ?? '');
    }

    /**
     * @return ConsoleType[]
     */
    public function platform(): array
    {
        $platforms = (string) ($this->pluck('trophyTitlePlatform') ?? '');
        if ($platforms === '') {
            return [];
        }

        $values = array_filter(array_map('trim', explode(',', $platforms)), static fn ($value) => $value !== '');

        return array_map(static fn (string $value): ConsoleType => new ConsoleType($value), $values);
    }

    public function trophySetVersion(): string
    {
        return (string) ($this->pluck('trophySetVersion') ?? '');
    }

    public function lastUpdatedDateTime(): string
    {
        return (string) ($this->pluck('lastUpdatedDateTime') ?? '');
    }

    public function trophyGroups(): array
    {
        return TrophyGroup::forTitle($this->httpClient, $this->accountId, $this->npCommunicationId, $this->serviceName);
    }

    protected function fetch(): object
    {
        return $this->httpClient
            ->get(
                'trophy/v1/users/' . $this->accountId . '/npCommunicationIds/' . $this->npCommunicationId . '/trophyGroups',
                [
                    'npServiceName' => $this->serviceName,
                ]
            )
            ->getJson();
    }
}
