<?php

declare(strict_types=1);

class TrophyListFilter
{
    private int $page;

    public function __construct(int $page)
    {
        $this->page = max($page, 1);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromArray(array $queryParameters): self
    {
        $page = $queryParameters['page'] ?? 1;

        if (!is_numeric($page)) {
            $page = 1;
        }

        return new self((int) $page);
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
     * @return array<string, int>
     */
    public function getFilterParameters(): array
    {
        return [];
    }

    /**
     * @return array<string, int>
     */
    public function toQueryParameters(): array
    {
        return $this->withPage($this->page);
    }

    /**
     * @return array<string, int>
     */
    public function withPage(int $page): array
    {
        $parameters = $this->getFilterParameters();
        $parameters['page'] = max($page, 1);

        return $parameters;
    }
}

