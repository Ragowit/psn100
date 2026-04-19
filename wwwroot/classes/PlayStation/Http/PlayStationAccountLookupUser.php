<?php

declare(strict_types=1);

require_once __DIR__ . '/../Dto/PsnTrophySummaryDto.php';

final readonly class PlayStationAccountLookupUser
{
    public function __construct(
        private string $accountId,
        private string $onlineId,
        private ?string $aboutMe,
        private ?string $country,
        private PsnTrophySummaryDto $trophySummary,
    ) {
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function onlineId(): string
    {
        return $this->onlineId;
    }

    public function aboutMe(): ?string
    {
        return $this->aboutMe;
    }

    public function country(): ?string
    {
        return $this->country;
    }

    public function trophySummary(): PsnTrophySummaryDto
    {
        return $this->trophySummary;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $profile = $payload['profile'] ?? null;
        if (!is_array($profile)) {
            $profile = $payload;
        }

        $accountId = $profile['accountId'] ?? null;
        if (!is_string($accountId) || trim($accountId) === '') {
            throw new UnexpectedValueException('Missing or invalid accountId in PlayStation payload.');
        }

        $onlineId = $profile['onlineId'] ?? $profile['currentOnlineId'] ?? null;
        if (!is_string($onlineId) || trim($onlineId) === '') {
            throw new UnexpectedValueException('Missing or invalid onlineId in PlayStation payload.');
        }

        $aboutMe = $profile['aboutMe'] ?? null;
        if ($aboutMe !== null && !is_string($aboutMe)) {
            throw new UnexpectedValueException('Invalid aboutMe field in PlayStation payload.');
        }

        $country = $profile['country'] ?? null;
        if ($country !== null && !is_string($country)) {
            throw new UnexpectedValueException('Invalid country field in PlayStation payload.');
        }

        $trophySummary = $profile['trophySummary'] ?? [];
        if (!is_array($trophySummary)) {
            throw new UnexpectedValueException('Invalid trophySummary in PlayStation payload.');
        }

        return new self(
            accountId: $accountId,
            onlineId: $onlineId,
            aboutMe: $aboutMe,
            country: $country,
            trophySummary: new PsnTrophySummaryDto(
                self::toInt($trophySummary['level'] ?? 0),
                self::toInt($trophySummary['progress'] ?? 0),
                self::toInt($trophySummary['earnedTrophies']['platinum'] ?? 0),
                self::toInt($trophySummary['earnedTrophies']['gold'] ?? 0),
                self::toInt($trophySummary['earnedTrophies']['silver'] ?? 0),
                self::toInt($trophySummary['earnedTrophies']['bronze'] ?? 0),
            )
        );
    }

    private static function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return 0;
    }
}
