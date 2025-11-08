<?php

declare(strict_types=1);

namespace PsnApi;

final class TrophyTitle
{
    private HttpClient $httpClient;

    private string $npCommunicationId;

    private string $serviceName;

    private ?string $accountId;

    public function __construct(
        HttpClient $httpClient,
        string $npCommunicationId,
        string $serviceName = 'trophy',
        ?string $accountId = null
    ) {
        $this->httpClient = $httpClient;
        $this->npCommunicationId = $npCommunicationId;
        $this->serviceName = $serviceName;
        $this->accountId = $accountId;
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
        return TrophyGroup::forTitle(
            $this->httpClient,
            $this->accountId,
            $this->npCommunicationId,
            $this->serviceName
        );
    }
}
