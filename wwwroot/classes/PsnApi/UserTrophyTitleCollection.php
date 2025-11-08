<?php

declare(strict_types=1);

namespace PsnApi;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

final class UserTrophyTitleCollection implements IteratorAggregate
{
    private HttpClient $httpClient;

    private string $accountId;

    /**
     * @var list<UserTrophyTitle>|null
     */
    private ?array $titles = null;

    public function __construct(HttpClient $httpClient, string $accountId)
    {
        $this->httpClient = $httpClient;
        $this->accountId = $accountId;
    }

    public function getIterator(): Traversable
    {
        if ($this->titles === null) {
            $this->titles = $this->fetchAllTitles();
        }

        return new ArrayIterator($this->titles);
    }

    /**
     * @return list<UserTrophyTitle>
     */
    private function fetchAllTitles(): array
    {
        $titles = [];
        $offset = 0;
        $limit = 100;

        do {
            $response = $this->httpClient->get(
                'trophy/v1/users/' . $this->accountId . '/trophyTitles',
                [
                    'limit' => $limit,
                    'offset' => $offset,
                ]
            )->getJson();

            if (!is_object($response) || !isset($response->trophyTitles) || !is_array($response->trophyTitles)) {
                break;
            }

            foreach ($response->trophyTitles as $item) {
                if (!is_object($item)) {
                    continue;
                }

                $titles[] = new UserTrophyTitle($this->httpClient, $this->accountId, $item);
            }

            $offset += $limit;
            $total = isset($response->totalItemCount) ? (int) $response->totalItemCount : count($titles);
        } while ($offset < $total);

        return $titles;
    }
}
