<?php

declare(strict_types=1);

namespace PsnApi;

final class TrophyGroup
{
    private Client $client;

    private string $npCommunicationId;

    private string $serviceName;

    /** @var array<string, mixed> */
    private array $data;

    private ?string $accountId;

    /** @var list<Trophy>|null */
    private ?array $trophies = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(Client $client, string $npCommunicationId, string $serviceName, array $data, ?string $accountId = null)
    {
        $this->client = $client;
        $this->npCommunicationId = $npCommunicationId;
        $this->serviceName = $serviceName;
        $this->data = $data;
        $this->accountId = $accountId;
    }

    public function id(): string
    {
        return (string) ($this->data['trophyGroupId'] ?? ($this->data['groupId'] ?? ''));
    }

    public function name(): string
    {
        return (string) ($this->data['trophyGroupName'] ?? ($this->data['name'] ?? ''));
    }

    public function detail(): string
    {
        return (string) ($this->data['trophyGroupDetail'] ?? ($this->data['detail'] ?? ''));
    }

    public function iconUrl(): string
    {
        return (string) ($this->data['trophyGroupIconUrl'] ?? ($this->data['iconUrl'] ?? ''));
    }

    /**
     * @return list<Trophy>
     */
    public function trophies(): array
    {
        if ($this->trophies === null) {
            $path = $this->accountId === null
                ? sprintf(
                    '/api/trophy/v1/npCommunicationIds/%s/trophyGroups/%s/trophies',
                    rawurlencode($this->npCommunicationId),
                    rawurlencode($this->id())
                )
                : sprintf(
                    '/api/trophy/v1/users/%s/npCommunicationIds/%s/trophyGroups/%s/trophies',
                    rawurlencode($this->accountId),
                    rawurlencode($this->npCommunicationId),
                    rawurlencode($this->id())
                );

            $response = $this->client->get($path, [
                'npServiceName' => $this->serviceName,
            ]);

            $trophies = [];
            $responseTrophies = $response['trophies'] ?? [];
            if (is_array($responseTrophies)) {
                foreach ($responseTrophies as $trophyData) {
                    if (!is_array($trophyData)) {
                        continue;
                    }

                    $trophies[] = new Trophy($trophyData);
                }
            }

            $this->trophies = $trophies;
        }

        return $this->trophies;
    }
}
