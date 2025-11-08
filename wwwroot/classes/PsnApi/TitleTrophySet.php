<?php

declare(strict_types=1);

namespace PsnApi;

final class TitleTrophySet
{
    private Client $client;

    private string $npCommunicationId;

    private string $serviceName;

    /** @var list<TrophyGroup>|null */
    private ?array $trophyGroups = null;

    public function __construct(Client $client, string $npCommunicationId, string $serviceName)
    {
        $this->client = $client;
        $this->npCommunicationId = $npCommunicationId;
        $this->serviceName = $serviceName;
    }

    /**
     * @return list<TrophyGroup>
     */
    public function trophyGroups(): array
    {
        if ($this->trophyGroups === null) {
            $response = $this->client->get(
                sprintf(
                    '/api/trophy/v1/npCommunicationIds/%s/trophyGroups',
                    rawurlencode($this->npCommunicationId)
                ),
                [
                    'npServiceName' => $this->serviceName,
                ]
            );

            $groups = [];
            $responseGroups = $response['trophyGroups'] ?? [];
            if (is_array($responseGroups)) {
                foreach ($responseGroups as $groupData) {
                    if (!is_array($groupData)) {
                        continue;
                    }

                    $groups[] = new TrophyGroup(
                        $this->client,
                        $this->npCommunicationId,
                        $this->serviceName,
                        $groupData
                    );
                }
            }

            $this->trophyGroups = $groups;
        }

        return $this->trophyGroups;
    }
}
