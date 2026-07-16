<?php

declare(strict_types=1);

require_once __DIR__ . '/HomepageContentService.php';
require_once __DIR__ . '/HomepagePage.php';
require_once __DIR__ . '/HomepageViewModel.php';
require_once __DIR__ . '/HomepagePopularGamesFilter.php';

final readonly class HomepageController
{
    public function __construct(
        private HomepagePage $homepagePage,
    ) {
    }

    public static function fromDatabase(PDO $database): self
    {
        $contentService = new HomepageContentService($database);
        $homepagePage = new HomepagePage($contentService);

        return new self($homepagePage);
    }

    #[\NoDiscard]
    public function withTitle(string $title): self
    {
        return clone($this, [
            'homepagePage' => $this->homepagePage->withTitle($title),
        ]);
    }

    #[\NoDiscard]
    public function withNewGamesLimit(int $limit): self
    {
        return clone($this, [
            'homepagePage' => $this->homepagePage->withNewGamesLimit($limit),
        ]);
    }

    #[\NoDiscard]
    public function withNewDlcsLimit(int $limit): self
    {
        return clone($this, [
            'homepagePage' => $this->homepagePage->withNewDlcsLimit($limit),
        ]);
    }

    #[\NoDiscard]
    public function withPopularGamesLimit(int $limit): self
    {
        return clone($this, [
            'homepagePage' => $this->homepagePage->withPopularGamesLimit($limit),
        ]);
    }

    #[\NoDiscard]
    public function withPopularGamesFilter(HomepagePopularGamesFilter $filter): self
    {
        return clone($this, [
            'homepagePage' => $this->homepagePage->withPopularGamesFilter($filter),
        ]);
    }

    public function getViewModel(): HomepageViewModel
    {
        return $this->homepagePage->buildViewModel();
    }
}
