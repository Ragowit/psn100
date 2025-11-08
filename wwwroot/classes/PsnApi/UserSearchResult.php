<?php

declare(strict_types=1);

namespace PsnApi;

final class UserSearchResult extends AbstractResource
{
    private string $onlineId;

    private string $accountId;

    private string $country;

    private bool $profileLoaded = false;

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
        if ($this->country !== '') {
            return $this->country;
        }

        $country = $this->stringValue('country');

        if ($country === '') {
            $country = $this->stringValue('region');
        }

        if ($country === '') {
            $this->ensureProfileLoaded();

            $country = $this->stringValue('country');

            if ($country === '') {
                $country = $this->stringValue('region');
            }

            if ($country === '') {
                $languages = $this->pluck('languages');
                $country = $this->countryFromLanguages($languages);
            }
        }

        $this->country = $country;

        return $this->country;
    }

    public function avatarUrl(): string
    {
        $avatarUrl = $this->stringValue('avatarUrl');

        if ($avatarUrl !== '') {
            return $avatarUrl;
        }

        $this->ensureProfileLoaded();

        $avatarUrl = $this->stringValue('avatarUrl');

        if ($avatarUrl !== '') {
            return $avatarUrl;
        }

        $avatars = $this->avatarUrls();

        if ($avatars === []) {
            return '';
        }

        $first = reset($avatars);

        return is_string($first) ? $first : '';
    }

    /**
     * @return array<string, string>
     */
    public function avatarUrls(): array
    {
        $avatars = $this->pluck('avatars');

        if (!is_array($avatars)) {
            $this->ensureProfileLoaded();
            $avatars = $this->pluck('avatars');
        }

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

    public function hasPlus(): bool
    {
        $value = $this->pluck('isPlus');

        if (!is_bool($value)) {
            $value = $this->pluck('isPsPlus');
        }

        if (!is_bool($value)) {
            $this->ensureProfileLoaded();
            $value = $this->pluck('isPlus');
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (bool) $value;
        }

        return false;
    }

    public function aboutMe(): string
    {
        $aboutMe = $this->stringValue('aboutMe');

        if ($aboutMe !== '') {
            return $aboutMe;
        }

        $this->ensureProfileLoaded();

        return $this->stringValue('aboutMe');
    }

    protected function fetch(): object
    {
        return $this->httpClient->get('userProfile/v1/internal/users/' . $this->accountId . '/profiles')->getJson();
    }

    private function ensureProfileLoaded(): void
    {
        if ($this->profileLoaded) {
            return;
        }

        $this->profileLoaded = true;

        try {
            $profileData = $this->fetch();
        } catch (\Throwable $exception) {
            return;
        }

        if (!is_object($profileData)) {
            return;
        }

        $currentData = $this->getData();

        if (is_object($currentData)) {
            $merged = (object) array_replace((array) $currentData, (array) $profileData);
            $this->setData($merged);

            return;
        }

        $this->setData($profileData);
    }

    private function stringValue(string $path): string
    {
        $value = $this->pluck($path);

        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }

    private function countryFromLanguages($languages): string
    {
        if (is_array($languages)) {
            foreach ($languages as $language) {
                if (!is_string($language) || $language === '') {
                    continue;
                }

                $segments = explode('-', $language);
                $country = strtoupper((string) end($segments));

                if ($country !== '') {
                    return $country;
                }
            }
        }

        return '';
    }
}
