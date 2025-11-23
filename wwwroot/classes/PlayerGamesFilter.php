<?php

declare(strict_types=1);

class PlayerGamesFilter
{
    public const SORT_DATE = 'date';
    public const SORT_IN_GAME_MAX_RARITY = 'max-in-game-rarity';
    public const SORT_IN_GAME_RARITY = 'in-game-rarity';
    public const SORT_MAX_RARITY = 'max-rarity';
    public const SORT_NAME = 'name';
    public const SORT_RARITY = 'rarity';
    public const SORT_SEARCH = 'search';

    public const PLATFORM_PC = 'pc';
    public const PLATFORM_PS3 = 'ps3';
    public const PLATFORM_PS4 = 'ps4';
    public const PLATFORM_PS5 = 'ps5';
    public const PLATFORM_PSVITA = 'psvita';
    public const PLATFORM_PSVR = 'psvr';
    public const PLATFORM_PSVR2 = 'psvr2';

    private const ALLOWED_SORTS = [
        self::SORT_DATE,
        self::SORT_IN_GAME_MAX_RARITY,
        self::SORT_IN_GAME_RARITY,
        self::SORT_MAX_RARITY,
        self::SORT_NAME,
        self::SORT_RARITY,
        self::SORT_SEARCH,
    ];

    private const PLATFORM_KEYS = [
        self::PLATFORM_PC,
        self::PLATFORM_PS3,
        self::PLATFORM_PS4,
        self::PLATFORM_PS5,
        self::PLATFORM_PSVITA,
        self::PLATFORM_PSVR,
        self::PLATFORM_PSVR2,
    ];

    private const DEFAULT_LIMIT = 50;

    private string $search;
    private string $sort;
    private bool $completed;
    private bool $uncompleted;
    private bool $base;
    /**
     * @var array<int, string>
     */
    private array $platforms;
    private int $page;
    private int $limit;
    private bool $sortProvided;

    private function __construct()
    {
        $this->search = '';
        $this->sort = self::SORT_DATE;
        $this->completed = false;
        $this->uncompleted = false;
        $this->base = false;
        $this->platforms = [];
        $this->page = 1;
        $this->limit = self::DEFAULT_LIMIT;
        $this->sortProvided = false;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function fromArray(array $parameters): self
    {
        $filter = new self();

        $filter->search = isset($parameters['search']) ? (string) $parameters['search'] : '';

        $sort = isset($parameters['sort']) ? (string) $parameters['sort'] : '';
        if ($sort !== '' && in_array($sort, self::ALLOWED_SORTS, true)) {
            $filter->sort = $sort;
            $filter->sortProvided = true;
        } elseif (!empty($filter->search)) {
            $filter->sort = self::SORT_SEARCH;
        }

        $filter->completed = !empty($parameters['completed']);
        $filter->uncompleted = !empty($parameters['uncompleted']);

        if ($filter->completed && $filter->uncompleted) {
            // Selecting both checkboxes should behave like no completion filter at all.
            $filter->completed = false;
            $filter->uncompleted = false;
        }
        $filter->base = !empty($parameters['base']);

        foreach (self::PLATFORM_KEYS as $platformKey) {
            if (!empty($parameters[$platformKey])) {
                $filter->platforms[] = $platformKey;
            }
        }

        $page = isset($parameters['page']) && is_numeric($parameters['page'])
            ? (int) $parameters['page']
            : 1;
        $filter->page = max($page, 1);

        return $filter;
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function hasSearchTerm(): bool
    {
        return !empty($this->search);
    }

    public function shouldApplyFulltextCondition(): bool
    {
        return $this->hasSearchTerm() || $this->sort === self::SORT_SEARCH;
    }

    public function shouldIncludeScoreColumn(): bool
    {
        return $this->shouldApplyFulltextCondition();
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function isCompletedSelected(): bool
    {
        return $this->completed;
    }

    public function isUncompletedSelected(): bool
    {
        return $this->uncompleted;
    }

    public function isBaseSelected(): bool
    {
        return $this->base;
    }

    public function hasPlatformFilters(): bool
    {
        return $this->platforms !== [];
    }

    /**
     * @return array<int, string>
     */
    public function getPlatforms(): array
    {
        return $this->platforms;
    }

    public function isPlatformSelected(string $platformKey): bool
    {
        return in_array($platformKey, $this->platforms, true);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * @return array<string, string>
     */
    public function getFilterParameters(): array
    {
        $parameters = [];

        if ($this->search !== '') {
            $parameters['search'] = $this->search;
        }

        if ($this->sortProvided || $this->sort !== self::SORT_DATE) {
            $parameters['sort'] = $this->sort;
        }

        if ($this->completed) {
            $parameters['completed'] = 'true';
        }

        if ($this->uncompleted) {
            $parameters['uncompleted'] = 'true';
        }

        if ($this->base) {
            $parameters['base'] = 'true';
        }

        foreach ($this->platforms as $platform) {
            $parameters[$platform] = 'true';
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

    public function withPageNumber(int $page): self
    {
        $clone = clone $this;
        $clone->page = max($page, 1);

        return $clone;
    }
}
