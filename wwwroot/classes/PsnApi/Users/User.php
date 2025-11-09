<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

use Achievements\PsnApi\Client;
use Achievements\PsnApi\Exceptions\ApiException;

final class User
{
    private Client $client;

    private string $accountId;

    private string $onlineId;

    private string $aboutMe;

    /** @var array<string, string> */
    private array $avatarUrls;

    /** @var list<string> */
    private array $languages;

    private bool $isPlus;

    private bool $isOfficiallyVerified;

    private TrophySummary $summary;

    private ?UserTrophyTitleCollection $trophyTitles = null;

    public function __construct(Client $client, string $accountId, string $onlineId, string $aboutMe, array $avatarUrls, array $languages, bool $isPlus, bool $isOfficiallyVerified, TrophySummary $summary)
    {
        $this->client = $client;
        $this->accountId = $accountId;
        $this->onlineId = $onlineId;
        $this->aboutMe = $aboutMe;
        $this->avatarUrls = $avatarUrls;
        $this->languages = $languages;
        $this->isPlus = $isPlus;
        $this->isOfficiallyVerified = $isOfficiallyVerified;
        $this->summary = $summary;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function onlineId(): string
    {
        return $this->onlineId;
    }

    public function aboutMe(): string
    {
        return $this->aboutMe;
    }

    /**
     * @return array<string, string>
     */
    public function avatarUrls(): array
    {
        return $this->avatarUrls;
    }

    /**
     * @return list<string>
     */
    public function languages(): array
    {
        return $this->languages;
    }

    public function trophySummary(): TrophySummary
    {
        return $this->summary;
    }

    public function trophyTitles(): UserTrophyTitleCollection
    {
        if ($this->trophyTitles !== null) {
            return $this->trophyTitles;
        }

        $titles = [];
        $offset = 0;
        $authorizationHeaders = $this->client->authorizationHeaders();

        do {
            $response = $this->client->requestJson(
                'GET',
                sprintf(
                    'https://m.np.playstation.com/api/trophy/v1/users/%s/trophyTitles?offset=%d&limit=800',
                    rawurlencode($this->accountId),
                    $offset
                ),
                null,
                $authorizationHeaders
            );

            if (!isset($response['trophyTitles']) || !is_array($response['trophyTitles'])) {
                throw new ApiException('Unexpected response when retrieving user trophy titles.', 0, $response);
            }

            /** @var array<int, array<string, mixed>> $titleData */
            $titleData = $response['trophyTitles'];

            foreach ($titleData as $title) {
                $titles[] = new UserTrophyTitle($this->client, $this->accountId, $title);
            }

            $offset = isset($response['nextOffset']) ? (int) $response['nextOffset'] : null;
        } while ($offset !== null && $offset > 0);

        return $this->trophyTitles = new UserTrophyTitleCollection($titles);
    }
}
