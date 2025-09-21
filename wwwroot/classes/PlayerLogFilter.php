<?php

declare(strict_types=1);

class PlayerLogFilter
{
    public const SORT_DATE = 'date';
    public const SORT_RARITY = 'rarity';

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
    private array $platforms = [];

    private string $sort;

    private function __construct(string $sort)
    {
        $this->sort = $this->normaliseSort($sort);
    }

    public static function fromArray(array $parameters): self
    {
        $filter = new self(is_string($parameters['sort'] ?? null) ? (string) $parameters['sort'] : self::SORT_DATE);

        foreach (self::ALLOWED_PLATFORMS as $platform) {
            if (!empty($parameters[$platform])) {
                $filter->platforms[] = $platform;
            }
        }

        $filter->platforms = array_values(array_unique($filter->platforms));

        return $filter;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function isSort(string $sort): bool
    {
        return $this->sort === $this->normaliseSort($sort);
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

    private function normaliseSort(string $sort): string
    {
        if ($sort === self::SORT_RARITY) {
            return self::SORT_RARITY;
        }

        return self::SORT_DATE;
    }
}
