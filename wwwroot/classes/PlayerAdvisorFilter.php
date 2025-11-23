<?php

declare(strict_types=1);

class PlayerAdvisorFilter
{
    public const SORT_RARITY = 'rarity';
    public const SORT_IN_GAME_RARITY = 'in_game_rarity';

    /**
     * @var array<int, string>
     */
    private const ALLOWED_SORTS = [
        self::SORT_RARITY,
        self::SORT_IN_GAME_RARITY,
    ];

    private const SUPPORTED_PLATFORMS = [
        'pc',
        'ps3',
        'ps4',
        'ps5',
        'psvita',
        'psvr',
        'psvr2',
    ];

    private int $page;

    private string $sort;

    /**
     * @var array<int, string>
     */
    private array $platforms;

    private function __construct(int $page, string $sort, array $platforms)
    {
        $this->page = $page;
        $this->sort = $sort;
        $this->platforms = $platforms;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public static function fromArray(array $parameters): self
    {
        $page = 1;
        if (isset($parameters['page']) && is_numeric($parameters['page'])) {
            $page = max((int) $parameters['page'], 1);
        }

        $sort = self::SORT_RARITY;
        if (isset($parameters['sort']) && in_array($parameters['sort'], self::ALLOWED_SORTS, true)) {
            $sort = (string) $parameters['sort'];
        }

        $platforms = [];
        foreach (self::SUPPORTED_PLATFORMS as $platform) {
            if (!empty($parameters[$platform])) {
                $platforms[] = $platform;
            }
        }

        return new self($page, $sort, $platforms);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getOffset(int $limit): int
    {
        return ($this->page - 1) * $limit;
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

    public function isPlatformSelected(string $platform): bool
    {
        return in_array($platform, $this->platforms, true);
    }

    public function getSort(): string
    {
        return $this->sort;
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
}
