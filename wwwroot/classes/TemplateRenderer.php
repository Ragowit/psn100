<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/PaginationRenderer.php';

class TemplateRenderer
{
    private Database $database;

    private Utility $utility;

    private PaginationRenderer $paginationRenderer;

    public function __construct(Database $database, Utility $utility, PaginationRenderer $paginationRenderer)
    {
        $this->database = $database;
        $this->utility = $utility;
        $this->paginationRenderer = $paginationRenderer;
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function render(string $templatePath, array $variables = []): void
    {
        if ($variables !== []) {
            extract($variables, EXTR_SKIP);
        }

        $database = $this->database;
        $utility = $this->utility;
        $paginationRenderer = $this->paginationRenderer;

        require_once $templatePath;
    }
}
