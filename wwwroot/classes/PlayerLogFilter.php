<?php

declare(strict_types=1);

class PlayerLogFilter
{
    public const SORT_DATE = 'date';
    public const SORT_RARITY = 'rarity';
    public const SORT_IN_GAME_RARITY = 'in-game-rarity';

    /** @var array<int, string> */
    private const ALLOWED_PLATFORMS = [
        'pc',
        'ps3',
        'ps4',
        'ps5',
        'psvita',
        'psvr',
        'psvr2',
    ];

    /** @var array<int, string> */
    private array $platforms;

    private string $sort;

    private int $page;

    private function __construct(string $sort, int $page, array $platforms)
    {
        $this->sort = $this->normaliseSort($sort);
        $this->page = max($page, 1);
        $this->platforms = array_values(array_unique($platforms));
    }

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

        return new self($sort, $page, $platforms);
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function isSort(string $sort): bool
    {
        return $this->sort === $this->normaliseSort($sort);
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

    public function withPageNumber(int $page): self
    {
        return new self($this->sort, $page, $this->platforms);
    }

    private function normaliseSort(string $sort): string
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
