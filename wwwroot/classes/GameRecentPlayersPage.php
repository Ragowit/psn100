<?php

declare(strict_types=1);

require_once __DIR__ . '/GamePlayerFilter.php';
require_once __DIR__ . '/GameRecentPlayersService.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/GameLeaderboardPlayerNotFoundException.php';
require_once __DIR__ . '/Utility.php';

class GameRecentPlayersPage
{
    private GameDetails $game;

    private GameHeaderData $gameHeaderData;

    private GamePlayerFilter $filter;

    /**
     * @var GameRecentPlayer[]
     */
    private array $recentPlayers;

    private ?string $playerAccountId;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $gamePlayer;

    /**
     * @param GameRecentPlayer[] $recentPlayers
     * @param array<string, mixed>|null $gamePlayer
     */
    private function __construct(
        GameDetails $game,
        GameHeaderData $gameHeaderData,
        GamePlayerFilter $filter,
        array $recentPlayers,
        ?string $playerAccountId,
        ?array $gamePlayer
    ) {
        $this->game = $game;
        $this->gameHeaderData = $gameHeaderData;
        $this->filter = $filter;
        $this->recentPlayers = $recentPlayers;
        $this->playerAccountId = $playerAccountId;
        $this->gamePlayer = $gamePlayer;
    }

    /**
     * @param array<string, mixed> $queryParameters
     *
     * @throws GameNotFoundException
     * @throws GameLeaderboardPlayerNotFoundException
     */
    public static function create(
        GameRecentPlayersService $recentPlayersService,
        GameHeaderService $gameHeaderService,
        int $gameId,
        ?string $player,
        array $queryParameters
    ): self {
        $game = $recentPlayersService->getGame($gameId);
        if ($game === null) {
            throw new GameNotFoundException('Game not found.');
        }

        $filter = GamePlayerFilter::fromArray($queryParameters);

        $playerAccountId = null;
        $gamePlayer = null;
        if ($player !== null) {
            $playerAccountId = $recentPlayersService->getPlayerAccountId($player);

            if ($playerAccountId === null) {
                $gameIdValue = $game->getId();
                $gameName = $game->getName();

                throw new GameLeaderboardPlayerNotFoundException($gameIdValue, $gameName);
            }

            $gamePlayer = $recentPlayersService->getGamePlayer(
                $game->getNpCommunicationId(),
                $playerAccountId
            );
        }

        $recentPlayers = $recentPlayersService->getRecentPlayers(
            $game->getNpCommunicationId(),
            $filter
        );

        $gameHeaderData = $gameHeaderService->buildHeaderData($game);

        return new self(
            $game,
            $gameHeaderData,
            $filter,
            $recentPlayers,
            $playerAccountId,
            $gamePlayer
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

    public function getFilter(): GamePlayerFilter
    {
        return $this->filter;
    }

    /**
     * @return GameRecentPlayer[]
     */
    public function getRecentPlayers(): array
    {
        return $this->recentPlayers;
    }

    public function getPlayerAccountId(): ?string
    {
        return $this->playerAccountId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGamePlayer(): ?array
    {
        return $this->gamePlayer;
    }

    public function getGameSlug(Utility $utility): string
    {
        return $this->game->getId() . '-' . $utility->slugify($this->game->getName());
    }

    public function getPageTitle(): string
    {
        return $this->game->getName() . ' Recent Players ~ PSN 100%';
    }
}
