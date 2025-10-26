<?php

declare(strict_types=1);

require_once __DIR__ . '/MaintenancePage.php';

final class MaintenancePageRenderer
{
    public function render(MaintenancePage $page): string
    {
        $title = $this->escape($page->getTitle());
        $heading = $this->escape($page->getHeading());
        $description = $this->escape($page->getDescription());
        $author = $this->escape($page->getAuthor());
        $message = nl2br($this->escape($page->getMessage()));
        $stylesheets = $this->renderStylesheets($page->getStylesheets());

        return <<<HTML
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{$description}">
        <meta name="author" content="{$author}">
{$stylesheets}
        <title>{$title}</title>
    </head>
    <body>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-6 text-center">
                    <h1 class="display-5 mb-3">{$heading}</h1>
                    <p class="lead mb-0">{$message}</p>
                </div>
            </div>
        </div>
    </body>
</html>
HTML
        . "\n";
    }

    /**
     * @param MaintenancePageStylesheet[] $stylesheets
     */
    private function renderStylesheets(array $stylesheets): string
    {
        if ($stylesheets === []) {
            return '';
        }

        $links = array_map(
            function (MaintenancePageStylesheet $stylesheet): string {
                $attributes = [
                    sprintf('rel="%s"', $this->escape($stylesheet->getRel())),
                    sprintf('href="%s"', $this->escape($stylesheet->getHref())),
                ];

                $integrity = $stylesheet->getIntegrity();
                if ($integrity !== null && $integrity !== '') {
                    $attributes[] = sprintf('integrity="%s"', $this->escape($integrity));
                }

                $crossorigin = $stylesheet->getCrossorigin();
                if ($crossorigin !== null && $crossorigin !== '') {
                    $attributes[] = sprintf('crossorigin="%s"', $this->escape($crossorigin));
                }

                return '        <link ' . implode(' ', $attributes) . '>';
            },
            $stylesheets
        );

        return implode("\n", $links);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
