<?php

declare(strict_types=1);

readonly class PlayerGamesFilter
{
    public const string SORT_DATE = 'date';
    public const string SORT_IN_GAME_MAX_RARITY = 'max-in-game-rarity';
    public const string SORT_IN_GAME_RARITY = 'in-game-rarity';
    public const string SORT_MAX_RARITY = 'max-rarity';
    public const string SORT_NAME = 'name';
    public const string SORT_RARITY = 'rarity';
    public const string SORT_SEARCH = 'search';

    public const string PLATFORM_PC = 'pc';
    public const string PLATFORM_PS3 = 'ps3';
    public const string PLATFORM_PS4 = 'ps4';
    public const string PLATFORM_PS5 = 'ps5';
    public const string PLATFORM_PSVITA = 'psvita';
    public const string PLATFORM_PSVR = 'psvr';
    public const string PLATFORM_PSVR2 = 'psvr2';

    /**
     * @var list<string>
     */
    private const array ALLOWED_SORTS = [
        self::SORT_DATE,
        self::SORT_IN_GAME_MAX_RARITY,
        self::SORT_IN_GAME_RARITY,
        self::SORT_MAX_RARITY,
        self::SORT_NAME,
        self::SORT_RARITY,
        self::SORT_SEARCH,
    ];

    /**
     * @var list<string>
     */
    private const array PLATFORM_KEYS = [
        self::PLATFORM_PC,
        self::PLATFORM_PS3,
        self::PLATFORM_PS4,
        self::PLATFORM_PS5,
        self::PLATFORM_PSVITA,
        self::PLATFORM_PSVR,
        self::PLATFORM_PSVR2,
    ];

    private const int DEFAULT_LIMIT = 50;

    /**
     * @param list<string> $platforms
     */
    private function __construct(
        final private string $search,
        final private string $sort,
        final private bool $completed,
        final private bool $uncompleted,
        final private bool $base,
        final private array $platforms,
        final private int $page,
        final private int $limit,
        final private bool $sortProvided,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[\NoDiscard]
    public static function fromArray(array $parameters): self
    {
        $search = isset($parameters['search']) ? (string) $parameters['search'] : '';

        $sort = isset($parameters['sort']) ? (string) $parameters['sort'] : '';
        $sortProvided = false;
        if ($sort !== '' && in_array($sort, self::ALLOWED_SORTS, true)) {
            $sortProvided = true;
        } elseif ($search !== '') {
            $sort = self::SORT_SEARCH;
        } else {
            $sort = self::SORT_DATE;
        }

        $completed = !empty($parameters['completed']);
        $uncompleted = !empty($parameters['uncompleted']);

        if ($completed && $uncompleted) {
            // Selecting both checkboxes should behave like no completion filter at all.
            $completed = false;
            $uncompleted = false;
        }

        $platforms = [];
        foreach (self::PLATFORM_KEYS as $platformKey) {
            if (!empty($parameters[$platformKey])) {
                $platforms[] = $platformKey;
            }
        }

        $page = isset($parameters['page']) && is_numeric($parameters['page'])
            ? (int) $parameters['page']
            : 1;

        return new self(
            $search,
            $sort,
            $completed,
            $uncompleted,
            !empty($parameters['base']),
            $platforms,
            max($page, 1),
            self::DEFAULT_LIMIT,
            $sortProvided,
        );
    }

    public function getSearch(): string
    {
        return $this->search;
    }

    public function hasSearchTerm(): bool
    {
        return $this->search !== '';
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

    public function isSort(string $sort): bool
    {
        return $this->sort === $sort;
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
     * @return list<string>
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

    #[\NoDiscard]
    public function withPageNumber(int $page): self
    {
        return clone($this, ['page' => max($page, 1)]);
    }
}
