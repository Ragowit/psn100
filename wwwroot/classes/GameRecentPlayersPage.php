<?php

declare(strict_types=1);

require_once __DIR__ . '/GamePlayerFilter.php';
require_once __DIR__ . '/GameRecentPlayersService.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/GameLeaderboardPlayerNotFoundException.php';
require_once __DIR__ . '/Utility.php';

class GameRecentPlayersPage
{
    /**
     * @var array<string, mixed>
     */
    private array $game;

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
     * @param array<string, mixed> $game
     * @param GameRecentPlayer[] $recentPlayers
     * @param array<string, mixed>|null $gamePlayer
     */
    private function __construct(
        array $game,
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
                $gameIdValue = (int) ($game['id'] ?? 0);
                $gameName = (string) ($game['name'] ?? '');

                throw new GameLeaderboardPlayerNotFoundException($gameIdValue, $gameName);
            }

            $gamePlayer = $recentPlayersService->getGamePlayer(
                (string) $game['np_communication_id'],
                $playerAccountId
            );
        }

        $recentPlayers = $recentPlayersService->getRecentPlayers(
            (string) $game['np_communication_id'],
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

    /**
     * @return array<string, mixed>
     */
    public function getGame(): array
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
        $gameId = (int) ($this->game['id'] ?? 0);
        $gameName = (string) ($this->game['name'] ?? '');

        return $gameId . '-' . $utility->slugify($gameName);
    }

    public function getPageTitle(): string
    {
        $gameName = (string) ($this->game['name'] ?? '');

        return $gameName . ' Recent Players ~ PSN 100%';
    }
}
