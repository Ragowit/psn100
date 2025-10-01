<?php

declare(strict_types=1);

/**
 * Responsible for rendering Bootstrap pagination controls that match the site's
 * existing style. The renderer generates the HTML as a string so that templates
 * can decide where and how to output it.
 */
class PaginationRenderer
{
    /**
     * @param callable(int):array<string, string> $queryParametersFactory
     */
    public function render(
        int $currentPage,
        int $totalPages,
        callable $queryParametersFactory,
        ?string $ariaLabel = null
    ): string {
        $totalPages = max(1, $totalPages);
        $navAttributes = '';

        if ($ariaLabel !== null && $ariaLabel !== '') {
            $navAttributes = ' aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '"';
        }

        $buildUrl = static function (int $page) use ($queryParametersFactory): string {
            $parameters = $queryParametersFactory($page);

            if (!is_array($parameters)) {
                throw new InvalidArgumentException('Pagination parameter factory must return an array.');
            }

            return '?' . http_build_query($parameters);
        };

        $html = '<nav' . $navAttributes . '>';
        $html .= '<ul class="pagination justify-content-center">';

        if ($currentPage > 1) {
            $previousUrl = $buildUrl($currentPage - 1);
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($previousUrl, ENT_QUOTES, 'UTF-8') . '" aria-label="Previous">&lt;</a></li>';
        }

        if ($currentPage > 3) {
            $firstPageUrl = $buildUrl(1);
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($firstPageUrl, ENT_QUOTES, 'UTF-8') . '">1</a></li>';
            $html .= '<li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>';
        }

        for ($i = 2; $i >= 1; $i--) {
            $previousPage = $currentPage - $i;

            if ($previousPage > 0) {
                $pageUrl = $buildUrl($previousPage);
                $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '">' . $previousPage . '</a></li>';
            }
        }

        $currentUrl = $buildUrl($currentPage);
        $html .= '<li class="page-item active" aria-current="page"><a class="page-link" href="' . htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8') . '">' . $currentPage . '</a></li>';

        for ($i = 1; $i <= 2; $i++) {
            $nextPage = $currentPage + $i;

            if ($nextPage <= $totalPages) {
                $pageUrl = $buildUrl($nextPage);
                $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') . '">' . $nextPage . '</a></li>';
            }
        }

        if ($currentPage < $totalPages - 2) {
            $lastPageUrl = $buildUrl($totalPages);
            $html .= '<li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($lastPageUrl, ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a></li>';
        }

        if ($currentPage < $totalPages) {
            $nextUrl = $buildUrl($currentPage + 1);
            $html .= '<li class="page-item"><a class="page-link" href="' . htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8') . '" aria-label="Next">&gt;</a></li>';
        }

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }
}
