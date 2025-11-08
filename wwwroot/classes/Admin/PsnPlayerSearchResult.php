<?php

declare(strict_types=1);

final class PsnPlayerSearchResult
{
    private string $onlineId;

    private string $accountId;

    private string $country;

    private string $avatarUrl;

    /** @var array<string, string> */
    private array $avatars;

    private bool $isPlus;

    private string $aboutMe;

    public function __construct(string $onlineId, string $accountId, string $country, string $avatarUrl, array $avatars, bool $isPlus, string $aboutMe)
    {
        $this->onlineId = $onlineId;
        $this->accountId = $accountId;
        $this->country = $country;
        $this->avatarUrl = $avatarUrl;
        $this->avatars = $avatars;
        $this->isPlus = $isPlus;
        $this->aboutMe = $aboutMe;
    }

    public static function fromUserSearchResult(object $userSearchResult): self
    {
        $onlineId = method_exists($userSearchResult, 'onlineId') ? (string) $userSearchResult->onlineId() : '';
        $accountId = method_exists($userSearchResult, 'accountId') ? (string) $userSearchResult->accountId() : '';
        $country = method_exists($userSearchResult, 'country') ? (string) $userSearchResult->country() : '';
        $avatarUrl = method_exists($userSearchResult, 'avatarUrl') ? (string) $userSearchResult->avatarUrl() : '';
        $avatars = method_exists($userSearchResult, 'avatarUrls') ? (array) $userSearchResult->avatarUrls() : [];
        $isPlus = method_exists($userSearchResult, 'hasPlus') ? (bool) $userSearchResult->hasPlus() : false;
        $aboutMe = method_exists($userSearchResult, 'aboutMe') ? (string) $userSearchResult->aboutMe() : '';

        return new self($onlineId, $accountId, $country, $avatarUrl, $avatars, $isPlus, $aboutMe);
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getAvatarUrl(): string
    {
        return $this->avatarUrl;
    }

    /**
     * @return array<string, string>
     */
    public function getAvatars(): array
    {
        return $this->avatars;
    }

    public function isPlus(): bool
    {
        return $this->isPlus;
    }

    public function getAboutMe(): string
    {
        return $this->aboutMe;
    }
}
