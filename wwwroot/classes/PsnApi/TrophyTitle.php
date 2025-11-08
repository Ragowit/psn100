<?php

declare(strict_types=1);

namespace PsnApi;

final class TrophyTitle
{
    private Client $client;

    private string $accountId;

    /** @var array<string, mixed> */
    private array $data;

    /** @var list<TrophyGroup>|null */
    private ?array $trophyGroups = null;

    /**
     * @param array<string, mixed> $data
     */
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

    public function lastUpdatedDateTime(): string
    {
        return (string) ($this->data['lastUpdatedDateTime'] ?? '');
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

    public function trophySetVersion(): string
    {
        return (string) ($this->data['trophySetVersion'] ?? '');
    }

    public function serviceName(): string
    {
        return (string) ($this->data['npServiceName'] ?? '');
    }

    /**
     * @return list<TrophyTitlePlatform>
     */
    public function platform(): array
    {
        $rawPlatform = $this->data['trophyTitlePlatform'] ?? [];
        if (is_string($rawPlatform)) {
            $values = array_filter(array_map('trim', explode(',', $rawPlatform)), static fn (string $value): bool => $value !== '');
        } elseif (is_array($rawPlatform)) {
            $values = [];
            foreach ($rawPlatform as $value) {
                if (!is_string($value)) {
                    continue;
                }

                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }

                $values[] = $trimmed;
            }
        } else {
            $values = [];
        }

        return array_map(static fn (string $value): TrophyTitlePlatform => new TrophyTitlePlatform($value), $values);
    }

    /**
     * @return list<TrophyGroup>
     */
    public function trophyGroups(): array
    {
        if ($this->trophyGroups === null) {
            $response = $this->client->get(
                sprintf(
                    '/api/trophy/v1/users/%s/npCommunicationIds/%s/trophyGroups',
                    rawurlencode($this->accountId),
                    rawurlencode($this->npCommunicationId())
                ),
                [
                    'npServiceName' => $this->serviceName(),
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
                        $this->npCommunicationId(),
                        $this->serviceName(),
                        $groupData,
                        $this->accountId
                    );
                }
            }

            $this->trophyGroups = $groups;
        }

        return $this->trophyGroups;
    }
}
