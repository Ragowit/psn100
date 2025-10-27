<?php

declare(strict_types=1);

require_once __DIR__ . '/FooterViewModel.php';

final class FooterRenderer
{
    public function render(FooterViewModel $viewModel): string
    {
        $yearRangeLabel = htmlspecialchars($viewModel->getYearRangeLabel(), ENT_QUOTES, 'UTF-8');
        $releaseUrl = htmlspecialchars($viewModel->getReleaseUrl(), ENT_QUOTES, 'UTF-8');
        $versionLabel = htmlspecialchars($viewModel->getVersionLabel(), ENT_QUOTES, 'UTF-8');
        $changelogUrl = htmlspecialchars($viewModel->getChangelogUrl(), ENT_QUOTES, 'UTF-8');
        $issuesUrl = htmlspecialchars($viewModel->getIssuesUrl(), ENT_QUOTES, 'UTF-8');
        $creatorProfileUrl = htmlspecialchars($viewModel->getCreatorProfileUrl(), ENT_QUOTES, 'UTF-8');
        $creatorName = htmlspecialchars($viewModel->getCreatorName(), ENT_QUOTES, 'UTF-8');
        $contributorsUrl = htmlspecialchars($viewModel->getContributorsUrl(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <footer class="container">
            <hr>
            <div class="row">
                <div class="col-3">
                    &copy; {$yearRangeLabel}<br>
                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="{$releaseUrl}">{$versionLabel}</a> -
                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="{$changelogUrl}">Changelog</a>
                </div>
                <div class="col-6 text-center">
                    PSN100 is not affiliated with Sony or PlayStation in any way.<br>
                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="{$issuesUrl}">Issues</a>
                </div>
                <div class="col-3 text-end">
                    Created and maintained by <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="{$creatorProfileUrl}">{$creatorName}</a>.<br>
                    Development by
                    <a class="link-underline link-underline-opacity-0 link-underline-opacity-100-hover" href="{$contributorsUrl}">PSN100 Contributors</a>.
                </div>
            </div>
        </footer>
        HTML;
    }
}
