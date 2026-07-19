<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';

readonly class GameListFilter
{
    public const string SORT_ADDED = 'added';
    public const string SORT_COMPLETION = 'completion';
    public const string SORT_OWNERS = 'owners';
    public const string SORT_RARITY = 'rarity';
    public const string SORT_IN_GAME_RARITY = 'in-game-rarity';
    public const string SORT_SEARCH = 'search';

    public const string PLATFORM_PC = Platform::Pc->value;
    public const string PLATFORM_PS3 = Platform::Ps3->value;
    public const string PLATFORM_PS4 = Platform::Ps4->value;
    public const string PLATFORM_PS5 = Platform::Ps5->value;
    public const string PLATFORM_PSVITA = Platform::PsVita->value;
    public const string PLATFORM_PSVR = Platform::PsVr->value;
    public const string PLATFORM_PSVR2 = Platform::PsVr2->value;

    private function __construct(
        final private ?string $player,
        final private string $sort,
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
        $uncompletedOnly = self::toBool($queryParameters['filter'] ?? null);

        $platformFilters = [];
        foreach (Platform::values() as $platform) {
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

    #[\NoDiscard]
    public function withPlayer(?string $player): self
    {
        if ($player !== null) {
            $player = trim($player);
            $player = $player === '' ? null : $player;
        }

        return clone($this, ['player' => $player]);
    }

    #[\NoDiscard]
    public function withPage(int $page): self
    {
        return clone($this, ['page' => max($page, 1)]);
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
        return array_any(
            $this->platformFilters,
            static fn (bool $selected): bool => $selected
        );
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

        foreach (Platform::values() as $platform) {
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
        $sort = is_string($value) ? ($value |> trim(...) |> strtolower(...)) : '';

        return match ($sort) {
            self::SORT_ADDED,
            self::SORT_COMPLETION,
            self::SORT_OWNERS,
            self::SORT_RARITY,
            self::SORT_IN_GAME_RARITY => $sort,
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
            $value = $value |> trim(...) |> strtolower(...);

            if ($value === '' || $value === 'false' || $value === '0') {
                return false;
            }

            return true;
        }

        return false;
    }
}
