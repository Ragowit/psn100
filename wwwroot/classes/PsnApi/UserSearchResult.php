<?php

declare(strict_types=1);

namespace PsnApi;

final class UserSearchResult extends AbstractResource
{
    private string $onlineId;

    private string $accountId;

    private string $country;

    public function __construct(HttpClient $httpClient, string $onlineId, string $accountId, string $country, ?object $data = null)
    {
        parent::__construct($httpClient, $data);
        $this->onlineId = $onlineId;
        $this->accountId = $accountId;
        $this->country = $country;
    }

    public function onlineId(): string
    {
        return $this->onlineId;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function country(): string
    {
        return $this->country;
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

        $result = [];
        foreach ($avatars as $avatar) {
            if (!is_array($avatar) && !is_object($avatar)) {
                continue;
            }

            $size = is_array($avatar) ? ($avatar['size'] ?? null) : ($avatar->size ?? null);
            $url = is_array($avatar) ? ($avatar['url'] ?? null) : ($avatar->url ?? null);

            if (is_string($size) && is_string($url)) {
                $result[$size] = $url;
            }
        }

        return $result;
    }

    protected function fetch(): object
    {
        return $this->httpClient->get('userProfile/v1/internal/users/' . $this->accountId . '/profiles')->getJson();
    }
}
