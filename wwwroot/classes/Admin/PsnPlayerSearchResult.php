<?php

declare(strict_types=1);

final class PsnPlayerSearchResult
{
    private string $onlineId;

    private string $accountId;

    private string $languages;

    public function __construct(string $onlineId, string $accountId, string $languages)
    {
        $this->onlineId = $onlineId;
        $this->accountId = $accountId;
        $this->languages = $languages;
    }

    public static function fromUserSearchResult(object $userSearchResult): self
    {
        $onlineId = method_exists($userSearchResult, 'onlineId') ? (string) $userSearchResult->onlineId() : '';
        $accountId = method_exists($userSearchResult, 'accountId') ? (string) $userSearchResult->accountId() : '';
        $languages = '';

        if (method_exists($userSearchResult, 'languages')) {
            $languagesValue = $userSearchResult->languages();

            if (is_array($languagesValue)) {
                $normalized = [];

                foreach ($languagesValue as $language) {
                    if (!is_string($language)) {
                        continue;
                    }

                    $language = trim($language);

                    if ($language === '') {
                        continue;
                    }

                    if (!in_array($language, $normalized, true)) {
                        $normalized[] = $language;
                    }
                }

                $languages = implode(', ', $normalized);
            } elseif (is_string($languagesValue)) {
                $languages = trim($languagesValue);
            }
        }

        return new self($onlineId, $accountId, $languages);
    }

    public function getOnlineId(): string
    {
        return $this->onlineId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getLanguages(): string
    {
        return $this->languages;
    }
}
