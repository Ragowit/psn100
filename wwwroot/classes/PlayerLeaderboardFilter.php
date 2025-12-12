<?php

declare(strict_types=1);

readonly class PlayerLeaderboardFilter
{
    private ?string $country;
    private ?string $avatar;
    private int $page;

    public function __construct(?string $country, ?string $avatar, int $page)
    {
        $country = $country !== null ? trim($country) : null;
        $avatar = $avatar !== null ? trim($avatar) : null;

        $this->country = $country === '' ? null : $country;
        $this->avatar = $avatar === '' ? null : $avatar;
        $this->page = max($page, 1);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $country = isset($queryParameters['country']) ? (string) $queryParameters['country'] : null;
        $avatar = isset($queryParameters['avatar']) ? (string) $queryParameters['avatar'] : null;

        $page = $queryParameters['page'] ?? 1;
        if (!is_numeric($page)) {
            $page = 1;
        }

        return new self($country, $avatar, (int) $page);
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

    public function getPage(): int
    {
        return $this->page;
    }

    public function getOffset(int $limit): int
    {
        return ($this->page - 1) * $limit;
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
     * @return array<string, int|string>
     */
    public function toQueryParameters(): array
    {
        return $this->withPage($this->page);
    }

    /**
     * @return array<string, int|string>
     */
    public function withPage(int $page): array
    {
        $parameters = $this->getFilterParameters();
        $parameters['page'] = max($page, 1);

        return $parameters;
    }
}
