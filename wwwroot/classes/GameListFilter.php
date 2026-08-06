<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/GameListSort.php';
require_once __DIR__ . '/RequestParameter.php';

final readonly class GameListFilter
{
    private function __construct(
        final private ?string $player,
        final private GameListSort $sort,
        final private bool $sortSpecified,
        final private string $search,
        final private int $page,
        final private bool $uncompletedOnly,
        /**
         * @var array<string, bool>
         */
        final private array $platformFilters,
        /**
         * @var array<string, string>
         */
        final private array $originalParameters
    ) {
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    #[\NoDiscard]
    public static function fromArray(array $queryParameters): self
    {
        $originalParameters = self::extractOriginalParameters($queryParameters);
        $sortSpecified = array_key_exists('sort', $originalParameters);

        $player = self::sanitizeNullableString($queryParameters['player'] ?? null);
        $search = self::sanitizeString($queryParameters['search'] ?? null);
        $page = self::sanitizePage($queryParameters['page'] ?? null);
        $sort = self::normalizeSort($queryParameters['sort'] ?? null, $search, $sortSpecified);
        $uncompletedOnly = RequestParameter::toBool($queryParameters['filter'] ?? null);

        $platformFilters = [];
        foreach (Platform::values() as $platform) {
            $platformFilters[$platform] = RequestParameter::toBool($queryParameters[$platform] ?? null);
        }

        return new self(
            player: $player,
            sort: $sort,
            sortSpecified: $sortSpecified,
            search: $search,
            page: $page,
            uncompletedOnly: $uncompletedOnly,
            platformFilters: $platformFilters,
            originalParameters: $originalParameters,
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

    #[\NoDiscard]
    public function withPlayer(?string $player): self
    {
        return clone($this, ['player' => self::sanitizeNullableString($player)]);
    }

    #[\NoDiscard]
    public function withPage(int $page): self
    {
        return clone($this, ['page' => max($page, 1)]);
    }

    public function getSort(): GameListSort
    {
        return $this->sort;
    }

    public function isSort(GameListSort $sort): bool
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
        return $this->hasSearch() || $this->sort === GameListSort::Search;
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
        return in_array(true, $this->platformFilters, true);
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
        return $this->platformFilters
            |> array_filter(...)
            |> array_keys(...);
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function getQueryParametersForPagination(): array
    {
        $parameters = $this->originalParameters;

        if ($this->player !== null) {
            $parameters['player'] = $this->player;
        } else {
            unset($parameters['player']);
        }

        if ($this->sortSpecified) {
            $parameters['sort'] = $this->sort->value;
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

        foreach (Platform::values() as $platform) {
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
            $trimmed = ((string) $value) |> trim(...);

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
            return ((string) $value) |> trim(...);
        }

        return '';
    }

    private static function sanitizePage(mixed $value): int
    {
        $page = match (true) {
            is_int($value) => $value,
            is_numeric($value) => (int) $value,
            default => 1,
        };

        return max($page, 1);
    }

    private static function normalizeSort(mixed $value, string $search, bool $sortSpecified): GameListSort
    {
        $sort = GameListSort::tryFromMixed($value);

        return match ($sort) {
            GameListSort::Added,
            GameListSort::Completion,
            GameListSort::Owners,
            GameListSort::Rarity,
            GameListSort::InGameRarity => $sort,
            GameListSort::Search => ($search !== '' || $sortSpecified) ? GameListSort::Search : GameListSort::Added,
            null => $search !== '' ? GameListSort::Search : GameListSort::Added,
        };
    }
}
