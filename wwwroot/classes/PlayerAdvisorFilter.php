<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/PlayerAdvisorSort.php';

readonly class PlayerAdvisorFilter
{
    public const string SORT_RARITY = PlayerAdvisorSort::Rarity->value;
    public const string SORT_IN_GAME_RARITY = PlayerAdvisorSort::InGameRarity->value;

    private function __construct(
        final private int $page,
        final private PlayerAdvisorSort $sort,
        /**
         * @var array<int, string>
         */
        final private array $platforms,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[\NoDiscard]
    public static function fromArray(array $parameters): self
    {
        $page = 1;
        if (isset($parameters['page']) && is_numeric($parameters['page'])) {
            $page = max((int) $parameters['page'], 1);
        }

        $sort = PlayerAdvisorSort::tryFromMixed($parameters['sort'] ?? null) ?? PlayerAdvisorSort::Rarity;

        $platforms = [];
        foreach (Platform::values() as $platform) {
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

    #[\NoDiscard]
    public function withPage(int $page): self
    {
        return clone($this, ['page' => max($page, 1)]);
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
        return $this->sort->value;
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

        $parameters['sort'] = $this->sort->value;

        return $parameters;
    }
}
