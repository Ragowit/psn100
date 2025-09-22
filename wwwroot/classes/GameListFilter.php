<?php

declare(strict_types=1);

class GameListFilter
{
    public const SORT_ADDED = 'added';
    public const SORT_COMPLETION = 'completion';
    public const SORT_OWNERS = 'owners';
    public const SORT_RARITY = 'rarity';
    public const SORT_SEARCH = 'search';

    public const PLATFORM_PC = 'pc';
    public const PLATFORM_PS3 = 'ps3';
    public const PLATFORM_PS4 = 'ps4';
    public const PLATFORM_PS5 = 'ps5';
    public const PLATFORM_PSVITA = 'psvita';
    public const PLATFORM_PSVR = 'psvr';
    public const PLATFORM_PSVR2 = 'psvr2';

    /**
     * @var list<string>
     */
    private const PLATFORM_KEYS = [
        self::PLATFORM_PC,
        self::PLATFORM_PS3,
        self::PLATFORM_PS4,
        self::PLATFORM_PS5,
        self::PLATFORM_PSVITA,
        self::PLATFORM_PSVR,
        self::PLATFORM_PSVR2,
    ];

    private ?string $player;
    private string $sort;
    private bool $sortSpecified;
    private string $search;
    private int $page;
    private bool $uncompletedOnly;
    /**
     * @var array<string, bool>
     */
    private array $platformFilters;
    /**
     * @var array<string, string>
     */
    private array $originalParameters;

    private function __construct(
        ?string $player,
        string $sort,
        bool $sortSpecified,
        string $search,
        int $page,
        bool $uncompletedOnly,
        array $platformFilters,
        array $originalParameters
    ) {
        $this->player = $player;
        $this->sort = $sort;
        $this->sortSpecified = $sortSpecified;
        $this->search = $search;
        $this->page = $page;
        $this->uncompletedOnly = $uncompletedOnly;
        $this->platformFilters = $platformFilters;
        $this->originalParameters = $originalParameters;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $originalParameters = self::extractOriginalParameters($queryParameters);
        $sortSpecified = array_key_exists('sort', $originalParameters);

        $player = self::sanitizeNullableString($queryParameters['player'] ?? null);
        $search = self::sanitizeString($queryParameters['search'] ?? null);
        $page = self::sanitizePage($queryParameters['page'] ?? null);
        $sort = self::normalizeSort($queryParameters['sort'] ?? null, $search, $sortSpecified);
        $uncompletedOnly = self::toBool($queryParameters['filter'] ?? null);

        $platformFilters = [];
        foreach (self::PLATFORM_KEYS as $platform) {
            $platformFilters[$platform] = self::toBool($queryParameters[$platform] ?? null);
        }

        return new self(
            $player,
            $sort,
            $sortSpecified,
            $search,
            $page,
            $uncompletedOnly,
            $platformFilters,
            $originalParameters
        );
    }

    public function getPlayer(): ?string
    {
        return $this->player;
    }

    public function hasPlayer(): bool
    {
        return $this->player !== null;
    }

    public function withPlayer(?string $player): self
    {
        $clone = clone $this;
        if ($player !== null) {
            $player = trim($player);
            $player = $player === '' ? null : $player;
        }

        $clone->player = $player;

        return $clone;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function isSort(string $sort): bool
    {
        return $this->sort === $sort;
    }

    public function hasExplicitSort(): bool
    {
        return $this->sortSpecified;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function hasSearch(): bool
    {
        return $this->search !== '';
    }

    public function shouldApplySearch(): bool
    {
        return $this->hasSearch() || $this->sort === self::SORT_SEARCH;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getOffset(int $limit): int
    {
        return ($this->page - 1) * $limit;
    }

    public function shouldFilterUncompleted(): bool
    {
        return $this->uncompletedOnly;
    }

    public function shouldShowUncompletedOption(): bool
    {
        return $this->hasPlayer();
    }

    public function hasPlatformFilters(): bool
    {
        foreach ($this->platformFilters as $selected) {
            if ($selected) {
                return true;
            }
        }

        return false;
    }

    public function isPlatformSelected(string $platform): bool
    {
        return $this->platformFilters[$platform] ?? false;
    }

    /**
     * @return list<string>
     */
    public function getSelectedPlatforms(): array
    {
        $platforms = [];

        foreach (self::PLATFORM_KEYS as $platform) {
            if ($this->platformFilters[$platform]) {
                $platforms[] = $platform;
            }
        }

        return $platforms;
    }

    /**
     * @return array<string, string>
     */
    public function getQueryParametersForPagination(): array
    {
        $parameters = $this->originalParameters;

        if ($this->player !== null) {
            $parameters['player'] = $this->player;
        } else {
            unset($parameters['player']);
        }

        if ($this->sortSpecified) {
            $parameters['sort'] = $this->sort;
        } else {
            unset($parameters['sort']);
        }

        if ($this->search !== '') {
            $parameters['search'] = $this->search;
        } else {
            unset($parameters['search']);
        }

        if ($this->uncompletedOnly) {
            $parameters['filter'] = 'true';
        } else {
            unset($parameters['filter']);
        }

        foreach (self::PLATFORM_KEYS as $platform) {
            if ($this->platformFilters[$platform]) {
                $parameters[$platform] = 'true';
            } else {
                unset($parameters[$platform]);
            }
        }

        unset($parameters['page']);

        return $parameters;
    }

    /**
     * @param array<string, mixed> $queryParameters
     * @return array<string, string>
     */
    private static function extractOriginalParameters(array $queryParameters): array
    {
        $parameters = [];

        foreach ($queryParameters as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $parameters[$key] = (string) $value;
        }

        return $parameters;
    }

    private static function sanitizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value) || is_numeric($value)) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }

    private static function sanitizeString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private static function sanitizePage(mixed $value): int
    {
        if (is_int($value)) {
            $page = $value;
        } elseif (is_string($value) && is_numeric($value)) {
            $page = (int) $value;
        } elseif (is_numeric($value)) {
            $page = (int) $value;
        } else {
            $page = 1;
        }

        return max($page, 1);
    }

    private static function normalizeSort(mixed $value, string $search, bool $sortSpecified): string
    {
        $sort = is_string($value) ? strtolower(trim($value)) : '';

        return match ($sort) {
            self::SORT_ADDED,
            self::SORT_COMPLETION,
            self::SORT_OWNERS,
            self::SORT_RARITY => $sort,
            self::SORT_SEARCH => ($search !== '' || $sortSpecified) ? self::SORT_SEARCH : self::SORT_ADDED,
            default => $search !== '' ? self::SORT_SEARCH : self::SORT_ADDED,
        };
    }

    private static function toBool(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            if ($value === '' || $value === 'false' || $value === '0') {
                return false;
            }

            return true;
        }

        return false;
    }
}
