<?php

declare(strict_types=1);

namespace PsnApi;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

final class UserProfile extends AbstractResource
{
    private string $accountId;

    public function __construct(HttpClient $httpClient, string $accountId)
    {
        parent::__construct($httpClient);
        $this->accountId = $accountId;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function onlineId(): string
    {
        return (string) ($this->pluck('onlineId') ?? '');
    }

    public function aboutMe(): string
    {
        return (string) ($this->pluck('aboutMe') ?? '');
    }

    /**
     * @return array<string, string>
     */
    public function avatarUrls(): array
    {
        $avatars = $this->pluck('avatars');
        if (!is_array($avatars)) {
            return [];
        }

        $urls = [];
        foreach ($avatars as $avatar) {
            if (!is_array($avatar) && !is_object($avatar)) {
                continue;
            }

            $size = is_array($avatar) ? ($avatar['size'] ?? null) : ($avatar->size ?? null);
            $url = is_array($avatar) ? ($avatar['url'] ?? null) : ($avatar->url ?? null);

            if (is_string($size) && is_string($url)) {
                $urls[$size] = $url;
            }
        }

        return $urls;
    }

    public function hasPlus(): bool
    {
        return (bool) ($this->pluck('isPlus') ?? false);
    }

    public function country(): string
    {
        return (string) ($this->pluck('country') ?? '');
    }

    public function trophySummary(): TrophySummary
    {
        return new TrophySummary($this->httpClient, $this->accountId);
    }

    public function trophyTitles(): UserTrophyTitleCollection
    {
        return new UserTrophyTitleCollection($this->httpClient, $this->accountId);
    }

    protected function fetch(): object
    {
        return $this->httpClient
            ->get('userProfile/v1/internal/users/' . $this->accountId . '/profiles')
            ->getJson();
    }
}
