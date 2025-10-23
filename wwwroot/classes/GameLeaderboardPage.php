<?php

declare(strict_types=1);

require_once __DIR__ . '/GamePlayerFilter.php';
require_once __DIR__ . '/GameLeaderboardFilter.php';
require_once __DIR__ . '/GameLeaderboardRow.php';
require_once __DIR__ . '/GameLeaderboardService.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/GameLeaderboardPlayerNotFoundException.php';

class GameLeaderboardPage
{
    private GameDetails $game;

    private GameHeaderData $gameHeaderData;

    private GameLeaderboardFilter $filter;

    private int $totalPlayers;

    private int $limit;

    private int $offset;

    private int $totalPagesCount;

    /**
     * @var GameLeaderboardRow[]
     */
    private array $rows;

    private ?string $playerAccountId;

    /**
     * @param GameLeaderboardRow[] $rows
     */
    private function __construct(
        GameDetails $game,
        GameHeaderData $gameHeaderData,
        GameLeaderboardFilter $filter,
        int $totalPlayers,
        int $limit,
        int $offset,
        int $totalPagesCount,
        array $rows,
        ?string $playerAccountId
    ) {
        $this->game = $game;
        $this->gameHeaderData = $gameHeaderData;
        $this->filter = $filter;
        $this->totalPlayers = $totalPlayers;
        $this->limit = $limit;
        $this->offset = $offset;
        $this->totalPagesCount = $totalPagesCount;
        $this->rows = $rows;
        $this->playerAccountId = $playerAccountId;
    }

    /**
     * @param array<string, mixed> $queryParameters
     *
     * @throws GameNotFoundException
     * @throws GameLeaderboardPlayerNotFoundException
     */
    public static function create(
        GameLeaderboardService $leaderboardService,
        GameHeaderService $headerService,
        int $gameId,
        ?string $player,
        array $queryParameters
    ): self {
        $game = $leaderboardService->getGame($gameId);
        if ($game === null) {
            throw new GameNotFoundException('Game not found.');
        }

        $playerAccountId = null;
        if ($player !== null) {
            $playerAccountId = $leaderboardService->getPlayerAccountId($player);

            if ($playerAccountId === null) {
                $gameIdValue = $game->getId();
                $gameName = $game->getName();

                throw new GameLeaderboardPlayerNotFoundException($gameIdValue, $gameName);
            }
        }

        $filter = GameLeaderboardFilter::fromArray($queryParameters);
        $limit = GameLeaderboardService::PAGE_SIZE;
        $offset = $filter->getOffset($limit);
        $npCommunicationId = $game->getNpCommunicationId();
        $totalPlayers = $leaderboardService->getLeaderboardPlayerCount(
            $npCommunicationId,
            $filter
        );
        $rows = $leaderboardService->getLeaderboardRows(
            $npCommunicationId,
            $filter,
            $limit
        );
        $totalPagesCount = $limit > 0 ? (int) ceil($totalPlayers / $limit) : 0;

        $gameHeaderData = $headerService->buildHeaderData($game);

        return new self(
            $game,
            $gameHeaderData,
            $filter,
            $totalPlayers,
            $limit,
            $offset,
            $totalPagesCount,
            $rows,
            $playerAccountId
        );
    }

    public function getGame(): GameDetails
    {
        return $this->game;
    }

    public function getGameHeaderData(): GameHeaderData
    {
        return $this->gameHeaderData;
    }

    public function getFilter(): GameLeaderboardFilter
    {
        return $this->filter;
    }

    public function getTotalPlayers(): int
    {
        return $this->totalPlayers;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getTotalPagesCount(): int
    {
        return $this->totalPagesCount;
    }

    /**
     * @return GameLeaderboardRow[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    public function getPlayerAccountId(): ?string
    {
        return $this->playerAccountId;
    }

    public function getPage(): int
    {
        return $this->filter->getPage();
    }

    public function getPageTitle(): string
    {
        return $this->game->getName() . ' Leaderboard ~ PSN 100%';
    }

    public function getGameSlug(Utility $utility): string
    {
        return $this->game->getId() . '-' . $utility->slugify($this->game->getName());
    }
}
