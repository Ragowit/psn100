<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/PaginationRenderer.php';

class ApplicationContainer
{
    private Database $database;

    private Utility $utility;

    private PaginationRenderer $paginationRenderer;

    public function __construct(
        ?Database $database = null,
        ?Utility $utility = null,
        ?PaginationRenderer $paginationRenderer = null
    ) {
        $this->database = $database ?? new Database();
        $this->utility = $utility ?? new Utility();
        $this->paginationRenderer = $paginationRenderer ?? new PaginationRenderer();
    }

    public static function create(): self
    {
        return new self();
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getUtility(): Utility
    {
        return $this->utility;
    }

    public function getPaginationRenderer(): PaginationRenderer
    {
        return $this->paginationRenderer;
    }
}
