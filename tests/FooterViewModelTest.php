<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/FooterViewModel.php';

final class FooterViewModelTest extends TestCase
{
    public function testGettersReturnConfiguredValues(): void
    {
        $viewModel = new FooterViewModel(
            2019,
            2024,
            'v1.2.3',
            'https://example.com/releases',
            '/changelog',
            'https://example.com/issues',
            'Ragowit',
            '/player/Ragowit',
            'https://example.com/contributors'
        );

        $this->assertSame('2019-2024', $viewModel->getYearRangeLabel());
        $this->assertSame('v1.2.3', $viewModel->getVersionLabel());
        $this->assertSame('https://example.com/releases', $viewModel->getReleaseUrl());
        $this->assertSame('/changelog', $viewModel->getChangelogUrl());
        $this->assertSame('https://example.com/issues', $viewModel->getIssuesUrl());
        $this->assertSame('Ragowit', $viewModel->getCreatorName());
        $this->assertSame('/player/Ragowit', $viewModel->getCreatorProfileUrl());
        $this->assertSame('https://example.com/contributors', $viewModel->getContributorsUrl());
    }

    public function testGetYearRangeLabelReturnsSingleYearWhenStartYearAtOrAboveCurrent(): void
    {
        $viewModel = new FooterViewModel(
            2025,
            2024,
            'v1',
            'release-url',
            'changelog-url',
            'issues-url',
            'creator',
            'profile-url',
            'contributors-url'
        );

        $this->assertSame('2025', $viewModel->getYearRangeLabel());
    }

    public function testCreateDefaultConfiguresKnownValues(): void
    {
        $viewModel = FooterViewModel::createDefault();
        $currentYear = (int) date('Y');
        $expectedYearRangeLabel = $currentYear <= 2019 ? '2019' : '2019-' . $currentYear;

        $this->assertSame($expectedYearRangeLabel, $viewModel->getYearRangeLabel());
        $this->assertSame('v7.40', $viewModel->getVersionLabel());
        $this->assertSame('https://github.com/Ragowit/psn100/releases', $viewModel->getReleaseUrl());
        $this->assertSame('/changelog', $viewModel->getChangelogUrl());
        $this->assertSame('https://github.com/Ragowit/psn100/issues', $viewModel->getIssuesUrl());
        $this->assertSame('Ragowit', $viewModel->getCreatorName());
        $this->assertSame('/player/Ragowit', $viewModel->getCreatorProfileUrl());
        $this->assertSame('https://github.com/ragowit/psn100/graphs/contributors', $viewModel->getContributorsUrl());
    }
}
