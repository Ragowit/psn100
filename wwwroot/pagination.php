<?php

declare(strict_types=1);

/**
 * Render Bootstrap pagination links that match the site's existing style.
 *
 * @param callable(int):array<string, string> $queryParametersFactory
 */
function renderPagination(
    int $currentPage,
    int $totalPages,
    callable $queryParametersFactory,
    ?string $ariaLabel = null
): void {
    $totalPages = max(1, $totalPages);
    $navAttributes = '';

    if ($ariaLabel !== null && $ariaLabel !== '') {
        $navAttributes = ' aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '"';
    }

    $buildUrl = static function (int $page) use ($queryParametersFactory): string {
        $parameters = $queryParametersFactory($page);

        if (!is_array($parameters)) {
            throw new \InvalidArgumentException('Pagination parameter factory must return an array.');
        }

        return '?' . http_build_query($parameters);
    };
    ?>
    <nav<?= $navAttributes; ?>>
        <ul class="pagination justify-content-center">
            <?php if ($currentPage > 1) { ?>
                <li class="page-item"><a class="page-link" href="<?= $buildUrl($currentPage - 1); ?>" aria-label="Previous">&lt;</a></li>
            <?php } ?>

            <?php if ($currentPage > 3) { ?>
                <li class="page-item"><a class="page-link" href="<?= $buildUrl(1); ?>">1</a></li>
                <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
            <?php } ?>

            <?php for ($i = 2; $i >= 1; $i--) {
                $previousPage = $currentPage - $i;

                if ($previousPage > 0) {
                    ?>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl($previousPage); ?>"><?= $previousPage; ?></a></li>
                    <?php
                }
            } ?>

            <li class="page-item active" aria-current="page"><a class="page-link" href="<?= $buildUrl($currentPage); ?>"><?= $currentPage; ?></a></li>

            <?php for ($i = 1; $i <= 2; $i++) {
                $nextPage = $currentPage + $i;

                if ($nextPage <= $totalPages) {
                    ?>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl($nextPage); ?>"><?= $nextPage; ?></a></li>
                    <?php
                }
            } ?>

            <?php if ($currentPage < $totalPages - 2) { ?>
                <li class="page-item disabled"><a class="page-link" href="#" tabindex="-1" aria-disabled="true">...</a></li>
                <li class="page-item"><a class="page-link" href="<?= $buildUrl($totalPages); ?>"><?= $totalPages; ?></a></li>
            <?php } ?>

            <?php if ($currentPage < $totalPages) { ?>
                <li class="page-item"><a class="page-link" href="<?= $buildUrl($currentPage + 1); ?>" aria-label="Next">&gt;</a></li>
            <?php } ?>
        </ul>
    </nav>
    <?php
}
