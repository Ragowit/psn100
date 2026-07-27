<?php

declare(strict_types=1);

require_once __DIR__ . '/Platform.php';
require_once __DIR__ . '/PlayerLogSort.php';
require_once __DIR__ . '/RequestParameter.php';

final readonly class PlayerLogFilter
{
    private function __construct(
        final private PlayerLogSort $sort,
        final private int $page,
        /** @var array<int, string> */
        final private array $platforms,
    ) {
    }

    #[\NoDiscard]
    public static function fromArray(array $parameters): self
    {
        $page = 1;
        if (isset($parameters['page']) && is_numeric((string) $parameters['page'])) {
            $page = (int) $parameters['page'];
        }

        $platforms = [];
        foreach (Platform::values() as $platform) {
            if (RequestParameter::toBool($parameters[$platform] ?? null)) {
                $platforms[] = $platform;
            }
        }

        return new self(
            PlayerLogSort::fromMixed($parameters['sort'] ?? null),
            max($page, 1),
            $platforms |> array_unique(...) |> array_values(...),
        );
    }

    public function getSort(): PlayerLogSort
    {
        return $this->sort;
    }

    public function isSort(PlayerLogSort $sort): bool
    {
        return $this->sort === $sort;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    #[\NoDiscard]
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
    #[\NoDiscard]
    public function getFilterParameters(): array
    {
        $parameters = [];

        foreach ($this->platforms as $platform) {
            $parameters[$platform] = 'true';
        }

        $parameters['sort'] = $this->sort->value;

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
