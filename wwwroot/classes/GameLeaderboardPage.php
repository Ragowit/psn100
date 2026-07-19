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

final readonly class GameLeaderboardPage
{
    /**
     * @param GameLeaderboardRow[] $rows
     */
    private function __construct(
        final private GameDetails $game,
        final private GameHeaderData $gameHeaderData,
        final private GameLeaderboardFilter $filter,
        final private int $totalPlayers,
        final private int $limit,
        final private int $offset,
        final private int $totalPagesCount,
        final private array $rows,
        final private ?string $playerAccountId,
    ) {
    }

    /**
     * @param array<string, mixed> $queryParameters
     *
     * @throws GameNotFoundException
     * @throws GameLeaderboardPlayerNotFoundException
     */
    #[\NoDiscard]
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
        $npCommunicationId = $game->getNpCommunicationId();
        $totalPlayers = $leaderboardService->getLeaderboardPlayerCount(
            $npCommunicationId,
            $filter
        );
        $totalPagesCount = $limit > 0 ? (int) ceil($totalPlayers / $limit) : 0;

        $clampedPage = self::normalizePageNumber($filter->getPage(), $totalPagesCount);
        $filter = $filter->withPageNumber($clampedPage);
        $offset = $filter->getOffset($limit);
        $rows = $leaderboardService->getLeaderboardRows(
            $npCommunicationId,
            $filter,
            $limit
        );

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

    private static function normalizePageNumber(int $requestedPage, int $totalPages): int
    {
        $maximumPage = $totalPages > 0 ? $totalPages : 1;

        return min(max($requestedPage, 1), $maximumPage);
    }
}
