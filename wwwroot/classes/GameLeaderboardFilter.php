<?php

declare(strict_types=1);

final readonly class GameLeaderboardFilter extends GamePlayerFilter
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

        $pageValue = $queryParameters['page'] ?? 1;
        $page = is_numeric($pageValue) ? (int) $pageValue : 1;

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
     * @return array<string, int|string>
     */
    public function toQueryParameters(): array
    {
        $parameters = parent::getFilterParameters();
        $parameters['page'] = $this->page;

        return $parameters;
    }

    /**
     * @return array<string, int|string>
     */
    public function withPage(int $page): array
    {
        $parameters = parent::getFilterParameters();
        $parameters['page'] = max($page, 1);

        return $parameters;
    }
}
