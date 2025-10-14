<?php

declare(strict_types=1);

require_once __DIR__ . '/HomepageContentService.php';
require_once __DIR__ . '/HomepagePage.php';
require_once __DIR__ . '/HomepageViewModel.php';

final class HomepageController
{
    private HomepagePage $homepagePage;

    public function __construct(HomepagePage $homepagePage)
    {
        $this->homepagePage = $homepagePage;
    }

    public static function fromDatabase(PDO $database): self
    {
        $contentService = new HomepageContentService($database);
        $homepagePage = new HomepagePage($contentService);

        return new self($homepagePage);
    }

    public function withTitle(string $title): self
    {
        $this->homepagePage->setTitle($title);

        return $this;
    }

    public function withNewGamesLimit(int $limit): self
    {
        $this->homepagePage->setNewGamesLimit($limit);

        return $this;
    }

    public function withNewDlcsLimit(int $limit): self
    {
        $this->homepagePage->setNewDlcsLimit($limit);

        return $this;
    }

    public function withPopularGamesLimit(int $limit): self
    {
        $this->homepagePage->setPopularGamesLimit($limit);

        return $this;
    }

    public function getViewModel(): HomepageViewModel
    {
        return $this->homepagePage->buildViewModel();
    }
}
