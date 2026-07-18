<?php

declare(strict_types=1);

readonly class PlayerLogFilter
{
    public const string SORT_DATE = 'date';
    public const string SORT_RARITY = 'rarity';
    public const string SORT_IN_GAME_RARITY = 'in-game-rarity';

    /** @var array<int, string> */
    private const array ALLOWED_PLATFORMS = [
        'pc',
        'ps3',
        'ps4',
        'ps5',
        'psvita',
        'psvr',
        'psvr2',
    ];

    private function __construct(
        final private string $sort,
        final private int $page,
        /** @var array<int, string> */
        final private array $platforms,
    ) {
    }

    #[\NoDiscard]
    public static function fromArray(array $parameters): self
    {
        $sort = is_string($parameters['sort'] ?? null) ? (string) $parameters['sort'] : self::SORT_DATE;

        $page = 1;
        if (isset($parameters['page']) && is_numeric((string) $parameters['page'])) {
            $page = (int) $parameters['page'];
        }

        $platforms = [];
        foreach (self::ALLOWED_PLATFORMS as $platform) {
            if (!empty($parameters[$platform])) {
                $platforms[] = $platform;
            }
        }

        return new self(
            self::normaliseSort($sort),
            max($page, 1),
            $platforms |> array_unique(...) |> array_values(...),
        );
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function isSort(string $sort): bool
    {
        return $this->sort === self::normaliseSort($sort);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getOffset(int $limit): int
    {
        return ($this->page - 1) * $limit;
    }

    public function isPlatformSelected(string $platform): bool
    {
        return in_array($platform, $this->platforms, true);
    }

    /**
     * @return array<int, string>
     */
    public function getPlatforms(): array
    {
        return $this->platforms;
    }

    public function hasPlatformFilters(): bool
    {
        return $this->platforms !== [];
    }

    /**
     * @return array<string, string>
     */
    public function getFilterParameters(): array
    {
        $parameters = [];

        foreach ($this->platforms as $platform) {
            $parameters[$platform] = 'true';
        }

        $parameters['sort'] = $this->sort;

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

    #[\NoDiscard]
    public function withPageNumber(int $page): self
    {
        return clone($this, ['page' => max($page, 1)]);
    }

    private static function normaliseSort(string $sort): string
    {
        if ($sort === self::SORT_RARITY) {
            return self::SORT_RARITY;
        }

        if ($sort === self::SORT_IN_GAME_RARITY) {
            return self::SORT_IN_GAME_RARITY;
        }

        return self::SORT_DATE;
    }
}
