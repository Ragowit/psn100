<?php

declare(strict_types=1);

require_once __DIR__ . '/ChangelogDateGroup.php';
require_once __DIR__ . '/ChangelogEntry.php';
require_once __DIR__ . '/ChangelogEntryPresenter.php';
require_once __DIR__ . '/ChangelogPaginator.php';
require_once __DIR__ . '/ChangelogService.php';
require_once __DIR__ . '/Utility.php';

class ChangelogPage
{
    private const DEFAULT_TITLE = 'Changelog ~ PSN 100%';

    private ChangelogPaginator $paginator;

    /**
     * @var ChangelogDateGroup[]
     */
    private array $dateGroups;

    private function __construct(ChangelogPaginator $paginator, array $dateGroups)
    {
        $this->paginator = $paginator;
        $this->dateGroups = $dateGroups;
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function create(PDO $database, Utility $utility, array $queryParameters): self
    {
        $service = new ChangelogService($database);

        return self::fromService($service, $utility, $queryParameters);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public static function fromService(ChangelogService $service, Utility $utility, array $queryParameters): self
    {
        $requestedPage = self::resolvePageNumber($queryParameters['page'] ?? null);
        $totalChanges = $service->getTotalChangeCount();
        $paginator = new ChangelogPaginator($requestedPage, $totalChanges, ChangelogService::PAGE_SIZE);

        $entries = $service->getChanges($paginator);

        $presenters = array_map(
            static fn(ChangelogEntry $entry): ChangelogEntryPresenter => new ChangelogEntryPresenter($entry, $utility),
            $entries
        );

        $dateGroups = self::groupPresentersByDate($presenters);

        return new self($paginator, $dateGroups);
    }

    public function getTitle(): string
    {
        return self::DEFAULT_TITLE;
    }

    public function getPaginator(): ChangelogPaginator
    {
        return $this->paginator;
    }

    /**
     * @return ChangelogDateGroup[]
     */
    public function getDateGroups(): array
    {
        return $this->dateGroups;
    }

    public function getRangeStart(): int
    {
        return $this->paginator->getRangeStart();
    }

    public function getRangeEnd(): int
    {
        return $this->paginator->getRangeEnd();
    }

    public function getTotalCount(): int
    {
        return $this->paginator->getTotalCount();
    }

    public function getCurrentPage(): int
    {
        return $this->paginator->getCurrentPage();
    }

    public function getTotalPages(): int
    {
        return $this->paginator->getTotalPages();
    }

    public function getLastPageNumber(): int
    {
        return $this->paginator->getLastPageNumber();
    }

    public function hasPreviousPage(): bool
    {
        return $this->paginator->hasPreviousPage();
    }

    public function getPreviousPage(): int
    {
        return $this->paginator->getPreviousPage();
    }

    public function hasNextPage(): bool
    {
        return $this->paginator->hasNextPage();
    }

    public function getNextPage(): int
    {
        return $this->paginator->getNextPage();
    }

    private static function resolvePageNumber(mixed $page): int
    {
        if (is_array($page)) {
            $page = reset($page);
        }

        if (!is_scalar($page)) {
            return 1;
        }

        $validatedPage = filter_var(
            $page,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($validatedPage === false) {
            return 1;
        }

        return (int) $validatedPage;
    }

    /**
     * @param ChangelogEntryPresenter[] $presenters
     * @return ChangelogDateGroup[]
     */
    private static function groupPresentersByDate(array $presenters): array
    {
        if ($presenters === []) {
            return [];
        }

        $grouped = [];
        foreach ($presenters as $presenter) {
            $dateLabel = $presenter->getDateLabel();
            if (!array_key_exists($dateLabel, $grouped)) {
                $grouped[$dateLabel] = [];
            }

            $grouped[$dateLabel][] = $presenter;
        }

        $dateGroups = [];
        foreach ($grouped as $dateLabel => $entries) {
            $dateGroups[] = new ChangelogDateGroup($dateLabel, $entries);
        }

        return $dateGroups;
    }
}
