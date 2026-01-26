<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/PlayerRandomGamesPageContext.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerStatus.php';

final class PlayerRandomGamesPageContextTest extends TestCase
{
    public function testContextAggregatesDependencies(): void
    {
        $filter = PlayerRandomGamesFilter::fromArray(['ps5' => 'true']);
        $utility = new Utility();
        $randomGame = PlayerRandomGame::fromArray([
            'id' => 123,
            'np_communication_id' => 'NPWR12345_00',
            'name' => 'Random Game',
            'icon_url' => 'random.png',
            'platform' => 'PS5',
            'owners' => 1000,
            'difficulty' => '12.5',
            'platinum' => 1,
            'gold' => 2,
            'silver' => 3,
            'bronze' => 4,
            'rarity_points' => 500,
            'progress' => '50',
        ], $utility);
        $randomGames = [$randomGame];

        $playerSummary = new PlayerSummary(10, 4, 75.0, 12);
        $page = $this->createPage($filter, $randomGames, $playerSummary, PlayerStatus::NORMAL, 99);

        $context = PlayerRandomGamesPageContext::fromComponents(
            $page,
            $page->getPlayerSummary(),
            $page->getFilter(),
            'ExampleUser',
            99,
            PlayerStatus::NORMAL
        );

        $this->assertSame("ExampleUser's Random Games ~ PSN 100%", $context->getTitle());
        $this->assertSame('ExampleUser', $context->getPlayerOnlineId());
        $this->assertSame(99, $context->getPlayerAccountId());
        $this->assertSame($page, $context->getPlayerRandomGamesPage());
        $this->assertSame($page->getPlayerSummary(), $context->getPlayerSummary());
        $this->assertSame($page->getFilter(), $context->getFilter());
        $this->assertSame($randomGames, $context->getRandomGames());
        $this->assertTrue($context->shouldShowRandomGames());
        $this->assertFalse($context->shouldShowFlaggedMessage());
        $this->assertFalse($context->shouldShowPrivateMessage());

        $navigation = $context->getPlayerNavigation();
        $links = $navigation->getLinks();
        $this->assertSame('/player/ExampleUser/random', $links[5]->getUrl());
        $this->assertTrue($links[5]->isActive());

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
    }

    public function testContextReflectsPlayerStatuses(): void
    {
        $filter = PlayerRandomGamesFilter::fromArray([]);
        $playerSummary = new PlayerSummary(0, 0, null, 0);
        $randomGames = [];

        $flaggedPage = $this->createPage($filter, $randomGames, $playerSummary, PlayerStatus::FLAGGED, 50);
        $flaggedContext = PlayerRandomGamesPageContext::fromComponents(
            $flaggedPage,
            $flaggedPage->getPlayerSummary(),
            $flaggedPage->getFilter(),
            'FlaggedUser',
            50,
            PlayerStatus::FLAGGED
        );

        $this->assertTrue($flaggedContext->shouldShowFlaggedMessage());
        $this->assertFalse($flaggedContext->shouldShowRandomGames());

        $privatePage = $this->createPage($filter, $randomGames, $playerSummary, PlayerStatus::PRIVATE_PROFILE, 75);
        $privateContext = PlayerRandomGamesPageContext::fromComponents(
            $privatePage,
            $privatePage->getPlayerSummary(),
            $privatePage->getFilter(),
            'PrivateUser',
            75,
            PlayerStatus::PRIVATE_PROFILE
        );

        $this->assertTrue($privateContext->shouldShowPrivateMessage());
        $this->assertFalse($privateContext->shouldShowRandomGames());
    }

    /**
     * @param PlayerRandomGame[] $randomGames
     */
    private function createPage(
        PlayerRandomGamesFilter $filter,
        array $randomGames,
        PlayerSummary $playerSummary,
        PlayerStatus $playerStatus,
        int $accountId
    ): PlayerRandomGamesPage {
        $randomGamesService = new class($randomGames) extends PlayerRandomGamesService {
            /** @var PlayerRandomGame[] */
            private array $randomGames;

            public function __construct(array $randomGames)
            {
                $this->randomGames = $randomGames;
            }

            public function getRandomGames(int $accountId, PlayerRandomGamesFilter $filter, int $limit = 8): array
            {
                return $this->randomGames;
            }
        };

        $summaryService = new class($playerSummary) extends PlayerSummaryService {
            private PlayerSummary $summary;

            public function __construct(PlayerSummary $summary)
            {
                $this->summary = $summary;
            }

            public function getSummary(int $accountId): PlayerSummary
            {
                return $this->summary;
            }
        };

        return new PlayerRandomGamesPage(
            $randomGamesService,
            $summaryService,
            $filter,
            $accountId,
            $playerStatus
        );
    }
}
