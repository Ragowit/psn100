<?php

declare(strict_types=1);

final readonly class PlayStationUserSearchResult
{
    public function __construct(
        private string $onlineId,
        private ?string $country = null,
        private ?string $aboutMe = null,
    ) {
    }

    public function onlineId(): string
    {
        return $this->onlineId;
    }

    public function country(): ?string
    {
        return $this->country;
    }

    public function aboutMe(): ?string
    {
        return $this->aboutMe;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        $onlineId = $payload['onlineId'] ?? null;

        if (!is_string($onlineId) || $onlineId === '') {
            throw new UnexpectedValueException('Missing or invalid user onlineId in PlayStation payload.');
        }

        $country = $payload['country'] ?? null;
        if ($country !== null && !is_string($country)) {
            throw new UnexpectedValueException('Invalid user country in PlayStation payload.');
        }

        $aboutMe = $payload['aboutMe'] ?? null;
        if ($aboutMe !== null && !is_string($aboutMe)) {
            throw new UnexpectedValueException('Invalid user aboutMe in PlayStation payload.');
        }

        return new self($onlineId, $country, $aboutMe);
    }
}
