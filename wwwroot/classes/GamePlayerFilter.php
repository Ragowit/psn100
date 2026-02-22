<?php

declare(strict_types=1);

readonly class GamePlayerFilter
{
    private ?string $country;

    private ?string $avatar;

    public function __construct(?string $country, ?string $avatar)
    {
        $this->country = self::normalizeOptionalString($country);
        $this->avatar = self::normalizeOptionalString($avatar);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $country = self::readOptionalString($queryParameters, 'country');
        $avatar = self::readOptionalString($queryParameters, 'avatar');

        return new self($country, $avatar);
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function hasCountry(): bool
    {
        return $this->country !== null;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function hasAvatar(): bool
    {
        return $this->avatar !== null;
    }

    /**
     * @return array{country?: string, avatar?: string}
     */
    public function getFilterParameters(): array
    {
        $parameters = [];

        if ($this->country !== null) {
            $parameters['country'] = $this->country;
        }

        if ($this->avatar !== null) {
            $parameters['avatar'] = $this->avatar;
        }

        return $parameters;
    }

    /**
     * @return array<string, string>
     */
    public function toQueryParameters(): array
    {
        return $this->getFilterParameters();
    }

    /**
     * @return array<string, string>
     */
    public function withCountry(?string $country): array
    {
        $parameters = $this->getFilterParameters();
        $country = self::normalizeOptionalString($country);

        if ($country === null) {
            unset($parameters['country']);
        } else {
            $parameters['country'] = $country;
        }

        return $parameters;
    }

    /**
     * @return array<string, string>
     */
    public function withAvatar(?string $avatar): array
    {
        $parameters = $this->getFilterParameters();
        $avatar = self::normalizeOptionalString($avatar);

        if ($avatar === null) {
            unset($parameters['avatar']);
        } else {
            $parameters['avatar'] = $avatar;
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    private static function readOptionalString(array $queryParameters, string $key): ?string
    {
        $value = $queryParameters[$key] ?? null;

        if ($value === null || is_array($value)) {
            return null;
        }

        return self::normalizeOptionalString((string) $value);
    }

    private static function normalizeOptionalString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
