<?php

declare(strict_types=1);

namespace PsnApi;

use PsnApi\Exception\PsnApiException;

final class User
{
    private Client $client;

    private string $accountId;

    private ?string $onlineId;

    private ?string $country;

    /** @var array<string, mixed>|null */
    private ?array $profile = null;

    /** @var array<string, mixed>|null */
    private ?array $summary = null;

    private ?TrophyTitleCollection $trophyTitles = null;

    private function __construct(Client $client, string $accountId, ?string $onlineId = null, ?string $country = null)
    {
        if ($accountId === '') {
            throw new PsnApiException('Account id must not be empty.');
        }

        $this->client = $client;
        $this->accountId = $accountId;
        $this->onlineId = $onlineId;
        $this->country = $country;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function fromSearchMetadata(Client $client, string $accountId, array $metadata): self
    {
        $onlineId = isset($metadata['onlineId']) ? (string) $metadata['onlineId'] : null;
        $country = isset($metadata['country']) ? (string) $metadata['country'] : null;

        return new self($client, $accountId, $onlineId, $country);
    }

    public static function fromAccountId(Client $client, string $accountId): self
    {
        return new self($client, (string) $accountId);
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function onlineId(): string
    {
        if ($this->onlineId === null) {
            $this->loadProfile();
            $this->onlineId = (string) ($this->profile['onlineId'] ?? '');
        }

        return $this->onlineId;
    }

    public function country(): string
    {
        if ($this->country === null) {
            $this->loadProfile();
            $country = $this->profile['country'] ?? null;
            if ($country === null && isset($this->profile['personalDetail']['country'])) {
                $country = $this->profile['personalDetail']['country'];
            }

            $this->country = $country !== null ? (string) $country : '';
        }

        return $this->country;
    }

    public function aboutMe(): string
    {
        $this->loadProfile();

        return (string) ($this->profile['aboutMe'] ?? '');
    }

    public function hasPlus(): bool
    {
        $this->loadProfile();

        return (bool) ($this->profile['isPlus'] ?? false);
    }

    /**
     * @return array<string, string>
     */
    public function avatarUrls(): array
    {
        $this->loadProfile();

        $avatars = $this->profile['avatars'] ?? [];
        if (!is_array($avatars)) {
            return [];
        }

        $urls = [];
        foreach ($avatars as $avatar) {
            if (!is_array($avatar)) {
                continue;
            }

            $size = isset($avatar['size']) ? (string) $avatar['size'] : '';
            $url = isset($avatar['url']) ? (string) $avatar['url'] : '';

            if ($size === '' || $url === '') {
                continue;
            }

            $urls[strtolower($size)] = $url;
        }

        return $urls;
    }

    public function trophySummary(): UserTrophySummary
    {
        if ($this->summary === null) {
            $this->summary = $this->client->get(
                sprintf('/api/trophy/v1/users/%s/trophySummary', rawurlencode($this->accountId))
            );
        }

        return new UserTrophySummary($this->summary);
    }

    public function trophyTitles(): TrophyTitleCollection
    {
        if ($this->trophyTitles === null) {
            $titles = [];
            $offset = 0;
            $total = null;

            do {
                $response = $this->client->get(
                    sprintf('/api/trophy/v1/users/%s/trophyTitles', rawurlencode($this->accountId)),
                    [
                        'offset' => (string) $offset,
                        'limit' => '100',
                    ]
                );

                $responseTitles = $response['trophyTitles'] ?? [];
                if (!is_array($responseTitles)) {
                    $responseTitles = [];
                }

                foreach ($responseTitles as $titleData) {
                    if (!is_array($titleData)) {
                        continue;
                    }

                    $titles[] = new TrophyTitle($this->client, $this->accountId, $titleData);
                }

                $total = $total ?? (isset($response['totalItemCount']) ? (int) $response['totalItemCount'] : count($titles));
                $nextOffset = isset($response['nextOffset']) ? (int) $response['nextOffset'] : 0;

                if ($nextOffset <= $offset) {
                    break;
                }

                $offset = $nextOffset;
            } while ($total === null || $offset < $total);

            $this->trophyTitles = new TrophyTitleCollection($titles);
        }

        return $this->trophyTitles;
    }

    private function loadProfile(): void
    {
        if ($this->profile !== null) {
            return;
        }

        $this->profile = $this->client->get(
            sprintf('/api/userProfile/v1/internal/users/%s/profiles', rawurlencode($this->accountId))
        );
    }
}
