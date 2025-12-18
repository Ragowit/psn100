<?php

declare(strict_types=1);

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/PaginationRenderer.php';

class TemplateRenderer
{
    public function __construct(
        private readonly Database $database,
        private readonly Utility $utility,
        private readonly PaginationRenderer $paginationRenderer,
    ) {
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

        require $templatePath;
    }
}
