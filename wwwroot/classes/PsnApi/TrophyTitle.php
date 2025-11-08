<?php

declare(strict_types=1);

namespace PsnApi;

final class TrophyTitle
{
    private HttpClient $httpClient;

    private string $npCommunicationId;

    private string $serviceName;

    public function __construct(HttpClient $httpClient, string $npCommunicationId, string $serviceName = 'trophy')
    {
        $this->httpClient = $httpClient;
        $this->npCommunicationId = $npCommunicationId;
        $this->serviceName = $serviceName;
    }

    public function npCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @return TrophyGroup[]
     */
    public function trophyGroups(): array
    {
        return TrophyGroup::forTitle($this->httpClient, null, $this->npCommunicationId, $this->serviceName);
    }
}
