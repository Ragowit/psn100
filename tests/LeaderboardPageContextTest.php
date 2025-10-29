<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Leaderboard/AbstractLeaderboardPageContext.php';
require_once __DIR__ . '/../wwwroot/classes/Leaderboard/AbstractLeaderboardRow.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerLeaderboardDataProvider.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class LeaderboardPageContextTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        FakeLeaderboardPageContext::reset();
    }

    public function testFromGlobalsBuildsRowsAndFilterParameters(): void
    {
        $players = [
            [
                'online_id' => 'Alpha',
                'avatar_url' => 'alpha.png',
                'country' => 'US',
                'ranking' => 1,
                'rank_last_week' => 2,
                'ranking_country' => 3,
                'rank_country_last_week' => 4,
                'trophy_count_npwr' => 10,
                'trophy_count_sony' => 12,
            ],
            [
                'online_id' => 'Bravo',
                'avatar_url' => 'bravo.png',
                'country' => 'CA',
                'ranking' => 5,
                'rank_last_week' => 6,
                'ranking_country' => 7,
                'rank_country_last_week' => 8,
                'trophy_count_npwr' => 9,
                'trophy_count_sony' => 9,
            ],
        ];

        $dataProvider = new FakePlayerLeaderboardDataProvider($players, 3, 2);
        FakeLeaderboardPageContext::setDataProvider($dataProvider);

        $utility = new Utility();
        $database = new class extends PDO {
            public function __construct()
            {
            }
        };

        $context = FakeLeaderboardPageContext::fromGlobals(
            $database,
            $utility,
            [
                'country' => 'US',
                'page' => '2',
                'player' => '  alpha  ',
            ]
        );

        $this->assertSame(FakeLeaderboardPageContext::TITLE, $context->getTitle());
        $this->assertSame(['country' => 'US'], $context->getFilterQueryParameters());
        $this->assertSame(['country' => 'US', 'page' => 2], $context->getCurrentPageQueryParameters());
        $this->assertTrue($context->shouldShowCountryRank());
        $this->assertSame(2, $context->getLeaderboardPage()->getCurrentPage());

        $rows = $context->getRows();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertTrue($row instanceof FakeLeaderboardRow, 'Expected all rows to be FakeLeaderboardRow instances.');
        }
        $this->assertSame('Alpha', $rows[0]->getOnlineId());
        $this->assertSame('table-primary', $rows[0]->getRowCssClass());
        $this->assertSame('', $rows[1]->getRowCssClass());

        $this->assertCount(1, $dataProvider->countPlayersFilters);
        $this->assertSame(2, $dataProvider->countPlayersFilters[0]->getPage());

        $this->assertCount(1, $dataProvider->getPlayersCalls);
        $this->assertSame(2, $dataProvider->getPlayersCalls[0]['filter']->getPage());
        $this->assertSame(2, $dataProvider->getPlayersCalls[0]['limit']);
    }

    public function testFromGlobalsIgnoresEmptyHighlightedPlayer(): void
    {
        $players = [
            [
                'online_id' => 'Charlie',
                'avatar_url' => 'charlie.png',
                'country' => 'GB',
                'ranking' => 10,
                'rank_last_week' => 12,
                'ranking_country' => 3,
                'rank_country_last_week' => 4,
            ],
        ];

        $dataProvider = new FakePlayerLeaderboardDataProvider($players, 1, 25);
        FakeLeaderboardPageContext::setDataProvider($dataProvider);

        $utility = new Utility();
        $database = new class extends PDO {
            public function __construct()
            {
            }
        };

        $context = FakeLeaderboardPageContext::fromGlobals(
            $database,
            $utility,
            [
                'player' => '   ',
            ]
        );

        $this->assertSame([], $context->getFilterQueryParameters());
        $this->assertSame(['page' => 1], $context->getCurrentPageQueryParameters());
        $this->assertFalse($context->shouldShowCountryRank());

        $rows = $context->getRows();
        $this->assertCount(1, $rows);
        $this->assertSame('', $rows[0]->getRowCssClass());
    }
}

final class FakeLeaderboardPageContext extends AbstractLeaderboardPageContext
{
    public const TITLE = 'Fake Leaderboard Title';

    private static ?PlayerLeaderboardDataProvider $dataProvider = null;

    public static function setDataProvider(PlayerLeaderboardDataProvider $dataProvider): void
    {
        self::$dataProvider = $dataProvider;
    }

    public static function reset(): void
    {
        self::$dataProvider = null;
    }

    public function getTitle(): string
    {
        return self::TITLE;
    }

    protected static function createDataProvider(PDO $database): PlayerLeaderboardDataProvider
    {
        if (self::$dataProvider === null) {
            throw new RuntimeException('Data provider not configured');
        }

        return self::$dataProvider;
    }

    protected function createRow(
        array $player,
        PlayerLeaderboardFilter $filter,
        Utility $utility,
        ?string $highlightedPlayerId,
        array $filterParameters
    ): AbstractLeaderboardRow {
        return new FakeLeaderboardRow(
            $player,
            $filter,
            $utility,
            $highlightedPlayerId,
            $filterParameters
        );
    }
}

final class FakeLeaderboardRow extends AbstractLeaderboardRow
{
    public function __construct(
        array $player,
        PlayerLeaderboardFilter $filter,
        Utility $utility,
        ?string $highlightedPlayerId,
        array $filterParameters
    ) {
        parent::__construct(
            $player,
            $filter,
            $utility,
            $highlightedPlayerId,
            $filterParameters,
            'ranking',
            'rank_last_week',
            'ranking_country',
            'rank_country_last_week'
        );
    }
}

final class FakePlayerLeaderboardDataProvider implements PlayerLeaderboardDataProvider
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $players;

    private int $totalPlayers;

    private int $pageSize;

    /**
     * @var PlayerLeaderboardFilter[]
     */
    public array $countPlayersFilters = [];

    /**
     * @var array<int, array{filter: PlayerLeaderboardFilter, limit: int}>
     */
    public array $getPlayersCalls = [];

    /**
     * @param array<int, array<string, mixed>> $players
     */
    public function __construct(array $players, int $totalPlayers, int $pageSize)
    {
        $this->players = $players;
        $this->totalPlayers = $totalPlayers;
        $this->pageSize = $pageSize;
    }

    public function countPlayers(PlayerLeaderboardFilter $filter): int
    {
        $this->countPlayersFilters[] = $filter;

        return $this->totalPlayers;
    }

    public function getPlayers(PlayerLeaderboardFilter $filter, int $limit): array
    {
        $this->getPlayersCalls[] = [
            'filter' => $filter,
            'limit' => $limit,
        ];

        return $this->players;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }
}
