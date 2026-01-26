<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerAdvisorPageContext.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerStatus.php';

final class PlayerAdvisorPageContextTest extends TestCase
{
    public function testContextAggregatesDependencies(): void
    {
        $filter = PlayerAdvisorFilter::fromArray(['ps5' => 'true']);
        $playerSummary = new PlayerSummary(10, 3, 75.0, 25);
        $trophies = [$this->createAdvisableTrophy()];
        $page = $this->createPageStub($playerSummary, $filter, $trophies, true);

        $context = PlayerAdvisorPageContext::fromComponents(
            $page,
            $filter,
            'ExampleUser',
            123,
            PlayerStatus::NORMAL,
            '456789'
        );

        $this->assertSame($page, $context->getPlayerAdvisorPage());
        $this->assertSame($playerSummary, $context->getPlayerSummary());
        $this->assertSame($filter, $context->getFilter());
        $this->assertSame("ExampleUser's Trophy Advisor ~ PSN 100%", $context->getTitle());
        $this->assertSame('ExampleUser', $context->getPlayerOnlineId());
        $this->assertSame(123, $context->getPlayerAccountId());
        $this->assertTrue($context->shouldDisplayAdvisor());
        $this->assertTrue($context->getPlayerStatusNotice() === null);

        $navigation = $context->getPlayerNavigation();
        $links = $navigation->getLinks();
        $this->assertSame('/player/ExampleUser/advisor', $links[3]->getUrl());
        $this->assertTrue($links[3]->isActive());

        $platformOptions = $context->getPlatformFilterOptions()->getOptions();
        $ps5Option = null;

        foreach ($platformOptions as $option) {
            if ($option->getInputName() === 'ps5') {
                $ps5Option = $option;
                break;
            }
        }

        $this->assertTrue($ps5Option instanceof PlayerPlatformFilterOption);
        $this->assertTrue($ps5Option->isSelected());

        $this->assertTrue($context->getTrophyRarityFormatter() instanceof TrophyRarityFormatter);
    }

    public function testContextCreatesStatusNoticeForFlaggedPlayers(): void
    {
        $filter = PlayerAdvisorFilter::fromArray([]);
        $playerSummary = new PlayerSummary(0, 0, null, 0);
        $page = $this->createPageStub($playerSummary, $filter, [], false);

        $context = PlayerAdvisorPageContext::fromComponents(
            $page,
            $filter,
            'FlaggedUser',
            99,
            PlayerStatus::FLAGGED,
            '123456'
        );

        $notice = $context->getPlayerStatusNotice();
        $this->assertTrue($notice instanceof PlayerStatusNotice);
        $this->assertTrue($notice->isFlagged());
    }

    public function testContextCreatesStatusNoticeForPrivatePlayers(): void
    {
        $filter = PlayerAdvisorFilter::fromArray([]);
        $playerSummary = new PlayerSummary(0, 0, null, 0);
        $page = $this->createPageStub($playerSummary, $filter, [], false);

        $context = PlayerAdvisorPageContext::fromComponents(
            $page,
            $filter,
            'PrivateUser',
            88,
            PlayerStatus::PRIVATE_PROFILE,
            null
        );

        $notice = $context->getPlayerStatusNotice();
        $this->assertTrue($notice instanceof PlayerStatusNotice);
        $this->assertTrue($notice->isPrivateProfile());
    }

    /**
     * @param PlayerAdvisableTrophy[] $trophies
     */
    private function createPageStub(
        PlayerSummary $summary,
        PlayerAdvisorFilter $filter,
        array $trophies,
        bool $shouldDisplay
    ): PlayerAdvisorPage {
        return new class($summary, $filter, $trophies, $shouldDisplay) extends PlayerAdvisorPage {
            private PlayerSummary $summary;

            private PlayerAdvisorFilter $filter;

            /** @var PlayerAdvisableTrophy[] */
            private array $trophies;

            private bool $shouldDisplay;

            public function __construct(
                PlayerSummary $summary,
                PlayerAdvisorFilter $filter,
                array $trophies,
                bool $shouldDisplay
            ) {
                $this->summary = $summary;
                $this->filter = $filter;
                $this->trophies = $trophies;
                $this->shouldDisplay = $shouldDisplay;
            }

            public function getPlayerSummary(): PlayerSummary
            {
                return $this->summary;
            }

            public function getFilter(): PlayerAdvisorFilter
            {
                return $this->filter;
            }

            public function getCurrentPage(): int
            {
                return 1;
            }

            public function getPageSize(): int
            {
                return 50;
            }

            public function getOffset(): int
            {
                return 0;
            }

            public function shouldDisplayAdvisor(): bool
            {
                return $this->shouldDisplay;
            }

            public function getTotalTrophies(): int
            {
                return count($this->trophies);
            }

            public function getAdvisableTrophies(): array
            {
                return $this->trophies;
            }

            public function getTotalPages(): int
            {
                return 1;
            }

            public function getFilterParameters(): array
            {
                return $this->filter->getFilterParameters();
            }
        };
    }

    private function createAdvisableTrophy(): PlayerAdvisableTrophy
    {
        $utility = new Utility();

        return PlayerAdvisableTrophy::fromArray(
            [
                'trophy_id' => 42,
                'trophy_type' => 'gold',
                'trophy_name' => 'Advisable Trophy',
                'trophy_detail' => 'Complete the refactor.',
                'trophy_icon' => 'icon.png',
                'rarity_percent' => 12.5,
                'game_id' => 7,
                'game_name' => 'Sample Game',
                'game_icon' => 'game.png',
                'platform' => 'PS5',
            ],
            $utility
        );
    }
}
