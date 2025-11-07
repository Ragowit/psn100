<?php

declare(strict_types=1);

final class PsnPlayerSearchResult
{
    private string $onlineId;

    private string $accountId;

    private string $country;

    public function __construct(string $onlineId, string $accountId, string $country)
    {
        $this->onlineId = $onlineId;
        $this->accountId = $accountId;
        $this->country = $country;
    }

    public static function fromUserSearchResult(object $userSearchResult): self
    {
        $onlineId = method_exists($userSearchResult, 'onlineId') ? (string) $userSearchResult->onlineId() : '';
        $accountId = method_exists($userSearchResult, 'accountId') ? (string) $userSearchResult->accountId() : '';
        $country = method_exists($userSearchResult, 'country') ? (string) $userSearchResult->country() : '';

        return new self($onlineId, $accountId, $country);
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
}
