<?php

declare(strict_types=1);

final class PlayerStatusNotice
{
    private const TYPE_FLAGGED = 'flagged';
    private const TYPE_PRIVATE = 'private';
    private const DISPUTE_BASE_URL = 'https://github.com/Ragowit/psn100/issues';
    private const PRIVATE_PROFILE_URL = 'https://www.playstation.com/en-us/support/account/privacy-settings-psn/';

    private string $type;

    private string $message;

    private function __construct(string $type, string $message)
    {
        $this->type = $type;
        $this->message = $message;
    }

    public static function flagged(string $onlineId, ?string $accountId): self
    {
        $disputeUrl = self::createDisputeUrl($onlineId, $accountId);
        $message = sprintf(
            "This player has some funny looking trophy data. This doesn't necessarily mean cheating, but all data from this player will be excluded from site statistics and leaderboards. <a href=\"%s\">Dispute</a>?",
            htmlspecialchars($disputeUrl, ENT_QUOTES, 'UTF-8')
        );

        return new self(self::TYPE_FLAGGED, $message);
    }

    public static function privateProfile(): self
    {
        $message = sprintf(
            'This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="%s">private</a> profile.',
            htmlspecialchars(self::PRIVATE_PROFILE_URL, ENT_QUOTES, 'UTF-8')
        );

        return new self(self::TYPE_PRIVATE, $message);
    }

    public static function createDisputeUrl(string $onlineId, ?string $accountId): string
    {
        $query = ['q' => self::buildDisputeQuery($onlineId, $accountId)];

        return self::DISPUTE_BASE_URL . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isFlagged(): bool
    {
        return $this->type === self::TYPE_FLAGGED;
    }

    public function isPrivateProfile(): bool
    {
        return $this->type === self::TYPE_PRIVATE;
    }

    private static function buildDisputeQuery(string $onlineId, ?string $accountId): string
    {
        $accountSegment = '';

        if ($accountId !== null && $accountId !== '') {
            $accountSegment = sprintf(' OR %s', $accountId);
        }

        return sprintf('label:cheater %s%s', $onlineId, $accountSegment);
    }
}
