<?php

declare(strict_types=1);

require_once __DIR__ . '/FooterViewModel.php';
require_once __DIR__ . '/Html.php';

final class FooterRenderer
{
    public function render(FooterViewModel $viewModel): string
    {
        $yearRangeLabel = Html::escape($viewModel->getYearRangeLabel());
        $releaseUrl = Html::escape($viewModel->getReleaseUrl());
        $versionLabel = Html::escape($viewModel->getVersionLabel());
        $changelogUrl = Html::escape($viewModel->getChangelogUrl());
        $issuesUrl = Html::escape($viewModel->getIssuesUrl());
        $creatorProfileUrl = Html::escape($viewModel->getCreatorProfileUrl());
        $creatorName = Html::escape($viewModel->getCreatorName());
        $contributorsUrl = Html::escape($viewModel->getContributorsUrl());

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
