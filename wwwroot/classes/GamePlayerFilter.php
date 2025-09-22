<?php

declare(strict_types=1);

class GamePlayerFilter
{
    private ?string $country;

    private ?string $avatar;

    public function __construct(?string $country, ?string $avatar)
    {
        $country = $country !== null ? trim($country) : null;
        $avatar = $avatar !== null ? trim($avatar) : null;

        $this->country = $country === '' ? null : $country;
        $this->avatar = $avatar === '' ? null : $avatar;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $country = isset($queryParameters['country']) ? (string) $queryParameters['country'] : null;
        $avatar = isset($queryParameters['avatar']) ? (string) $queryParameters['avatar'] : null;

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
        $country = $country !== null ? trim($country) : null;

        if ($country === null || $country === '') {
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
        $avatar = $avatar !== null ? trim($avatar) : null;

        if ($avatar === null || $avatar === '') {
            unset($parameters['avatar']);
        } else {
            $parameters['avatar'] = $avatar;
        }

        return $parameters;
    }
}
