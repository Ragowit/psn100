<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerStatusNoticeType.php';

final readonly class PlayerStatusNotice
{
    private const string DISPUTE_BASE_URL = 'https://github.com/Ragowit/psn100/issues';
    private const string PRIVATE_PROFILE_URL = 'https://www.playstation.com/en-us/support/account/privacy-settings-psn/';

    private function __construct(
        final private PlayerStatusNoticeType $type,
        final private string $message,
    ) {}

    #[\NoDiscard]
    public static function flagged(string $onlineId, ?string $accountId): self
    {
        $disputeUrl = self::createDisputeUrl($onlineId, $accountId);
        $message = sprintf(
            "This player has some funny looking trophy data. This doesn't necessarily mean cheating, but all data from this player will be excluded from site statistics and leaderboards. <a href=\"%s\">Dispute</a>?",
            htmlspecialchars($disputeUrl, ENT_QUOTES, 'UTF-8')
        );

        return new self(PlayerStatusNoticeType::Flagged, $message);
    }

    #[\NoDiscard]
    public static function privateProfile(): self
    {
        $message = sprintf(
            'This player seems to have a <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="%s">private</a> profile.',
            htmlspecialchars(self::PRIVATE_PROFILE_URL, ENT_QUOTES, 'UTF-8')
        );

        return new self(PlayerStatusNoticeType::Private, $message);
    }

    #[\NoDiscard]
    public static function createDisputeUrl(string $onlineId, ?string $accountId): string
    {
        $query = http_build_query(
            ['q' => self::buildDisputeQuery($onlineId, $accountId)],
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return Uri\Rfc3986\Uri::parse(self::DISPUTE_BASE_URL)
            ?->withQuery($query)
            ->toRawString()
            ?? self::DISPUTE_BASE_URL . '?' . $query;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getType(): string
    {
        return $this->type->value;
    }

    public function isFlagged(): bool
    {
        return $this->type === PlayerStatusNoticeType::Flagged;
    }

    public function isPrivateProfile(): bool
    {
        return $this->type === PlayerStatusNoticeType::Private;
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
