<?php

declare(strict_types=1);

readonly class PlayerLeaderboardFilter
{
    private ?string $country;
    private ?string $avatar;
    private int $page;

    public function __construct(?string $country, ?string $avatar, int $page)
    {
        $this->country = self::normalizeOptionalString($country);
        $this->avatar = self::normalizeOptionalString($avatar);
        $this->page = max($page, 1);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $country = self::readOptionalString($queryParameters, 'country');
        $avatar = self::readOptionalString($queryParameters, 'avatar');
        $page = self::normalizePage($queryParameters['page'] ?? null);

        return new self($country, $avatar, $page);
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

    private static function normalizePage(mixed $value): int
    {
        if ($value === null || is_array($value) || !is_numeric($value)) {
            return 1;
        }

        return max((int) $value, 1);
    }
}
