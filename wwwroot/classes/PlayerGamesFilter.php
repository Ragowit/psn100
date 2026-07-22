<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/PlayerGamesSort.php';
require_once __DIR__ . '/RequestParameter.php';

final readonly class PlayerGamesFilter
{
    public const string SORT_DATE = PlayerGamesSort::Date->value;
    public const string SORT_IN_GAME_MAX_RARITY = PlayerGamesSort::InGameMaxRarity->value;
    public const string SORT_IN_GAME_RARITY = PlayerGamesSort::InGameRarity->value;
    public const string SORT_MAX_RARITY = PlayerGamesSort::MaxRarity->value;
    public const string SORT_NAME = PlayerGamesSort::Name->value;
    public const string SORT_RARITY = PlayerGamesSort::Rarity->value;
    public const string SORT_SEARCH = PlayerGamesSort::Search->value;

    private const int DEFAULT_LIMIT = 50;

    /**
     * @param list<string> $platforms
     */
    private function __construct(
        final private string $search,
        final private PlayerGamesSort $sort,
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

        $parsedSort = PlayerGamesSort::tryFromMixed($parameters['sort'] ?? null);
        $sortProvided = $parsedSort !== null;
        $sort = $parsedSort
            ?? ($search !== '' ? PlayerGamesSort::Search : PlayerGamesSort::Date);

        $completed = RequestParameter::toBool($parameters['completed'] ?? null);
        $uncompleted = RequestParameter::toBool($parameters['uncompleted'] ?? null);

        if ($completed && $uncompleted) {
            // Selecting both checkboxes should behave like no completion filter at all.
            $completed = false;
            $uncompleted = false;
        }

        $platforms = [];
        foreach (Platform::values() as $platformKey) {
            if (RequestParameter::toBool($parameters[$platformKey] ?? null)) {
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
            RequestParameter::toBool($parameters['base'] ?? null),
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
        return $this->hasSearchTerm() || $this->sort === PlayerGamesSort::Search;
    }

    public function shouldIncludeScoreColumn(): bool
    {
        return $this->shouldApplyFulltextCondition();
    }

    public function getSort(): PlayerGamesSort
    {
        return $this->sort;
    }

    public function isSort(PlayerGamesSort|string $sort): bool
    {
        if ($sort instanceof PlayerGamesSort) {
            return $this->sort === $sort;
        }

        return $this->sort->value === $sort;
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

        if ($this->sortProvided || $this->sort !== PlayerGamesSort::Date) {
            $parameters['sort'] = $this->sort->value;
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
    #[\NoDiscard]
    public function toQueryParameters(): array
    {
        return $this->withPage($this->page);
    }

    /**
     * @return array<string, int|string>
     */
    #[\NoDiscard]
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
