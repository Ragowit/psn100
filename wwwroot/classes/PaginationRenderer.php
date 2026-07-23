<?php

declare(strict_types=1);

require_once __DIR__ . '/Pagination.php';
require_once __DIR__ . '/Html.php';

/**
 * Responsible for rendering Bootstrap pagination controls that match the site's
 * existing style. The renderer generates the HTML as a string so that templates
 * can decide where and how to output it.
 */
final class PaginationRenderer
{
    /**
     * @param \Closure(int):array<string, string> $queryParametersFactory
     */
    public function render(
        int $currentPage,
        int $totalPages,
        \Closure $queryParametersFactory,
        ?string $ariaLabel = null
    ): string {
        $pagination = Pagination::create($currentPage, $totalPages);
        $navAttributes = '';

        if ($ariaLabel !== null && $ariaLabel !== '') {
            $navAttributes = ' aria-label="' . Html::escape($ariaLabel) . '"';
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

        foreach ($pagination->buildItems() as $item) {
            $html .= $item->render($buildUrl);
        }

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }
}
