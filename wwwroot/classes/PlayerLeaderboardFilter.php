<?php

declare(strict_types=1);

require_once __DIR__ . '/GamePlayerFilter.php';

final readonly class PlayerLeaderboardFilter extends GamePlayerFilter
{
    private int $page;

    public function __construct(?string $country, ?string $avatar, int $page)
    {
        parent::__construct($country, $avatar);
        $this->page = max($page, 1);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $baseFilter = parent::fromArray($queryParameters);
        $country = $baseFilter->getCountry();
        $avatar = $baseFilter->getAvatar();
        $page = self::normalizePage($queryParameters['page'] ?? null);

        return new self($country, $avatar, $page);
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getOffset(int $limit): int
    {
        return ($this->page - 1) * $limit;
    }

    /**
     * @return array{country?: string, avatar?: string}
     */
    public function getFilterParameters(): array
    {
        return parent::getFilterParameters();
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

    private static function normalizePage(mixed $value): int
    {
        if ($value === null || is_array($value) || !is_numeric($value)) {
            return 1;
        }

        return max((int) $value, 1);
    }
}
