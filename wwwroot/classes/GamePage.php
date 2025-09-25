<?php

declare(strict_types=1);

require_once __DIR__ . '/GameService.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/GameLeaderboardPlayerNotFoundException.php';
require_once __DIR__ . '/Game/GameHeaderData.php';
require_once __DIR__ . '/PageMetaData.php';
require_once __DIR__ . '/Utility.php';

class GamePage
{
    private GameService $gameService;

    private GameHeaderService $gameHeaderService;

    private Utility $utility;

    /**
     * @var array<string, mixed>
     */
    private array $game;

    private GameHeaderData $headerData;

    private string $sort;

    private ?int $playerAccountId;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $gamePlayer;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $trophyGroups = [];

    /**
     * @var array<string, array<string, mixed>|null>
     */
    private array $trophyGroupPlayers = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $trophiesByGroup = [];

    private ?string $playerOnlineId;

    /**
     * @param array<string, mixed> $queryParameters
     */
    public function __construct(
        GameService $gameService,
        GameHeaderService $gameHeaderService,
        Utility $utility,
        int $gameId,
        array $queryParameters = [],
        ?string $playerOnlineId = null
    ) {
        $this->gameService = $gameService;
        $this->gameHeaderService = $gameHeaderService;
        $this->utility = $utility;
        $this->playerOnlineId = $playerOnlineId !== null ? trim($playerOnlineId) : null;

        $this->game = $this->loadGame($gameId);
        $this->headerData = $this->gameHeaderService->buildHeaderData($this->game);
        $this->sort = $this->gameService->resolveSort($queryParameters);
        $this->playerAccountId = $this->resolvePlayerAccountId();
        $this->gamePlayer = $this->playerAccountId !== null
            ? $this->gameService->getGamePlayer($this->game['np_communication_id'], $this->playerAccountId)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadGame(int $gameId): array
    {
        $game = $this->gameService->getGame($gameId);

        if ($game === null) {
            throw new GameNotFoundException('Game not found: ' . $gameId);
        }

        return $game;
    }

    private function resolvePlayerAccountId(): ?int
    {
        if ($this->playerOnlineId === null || $this->playerOnlineId === '') {
            return null;
        }

        $accountId = $this->gameService->getPlayerAccountId($this->playerOnlineId);

        if ($accountId === null) {
            throw new GameLeaderboardPlayerNotFoundException(
                (int) $this->game['id'],
                (string) $this->game['name']
            );
        }

        return $accountId;
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
        return $this->headerData;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    public function getPlayerAccountId(): ?int
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTrophyGroups(): array
    {
        if ($this->trophyGroups === []) {
            $this->trophyGroups = $this->gameService->getTrophyGroups($this->game['np_communication_id']);
        }

        return $this->trophyGroups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTrophyGroupPlayer(string $groupId): ?array
    {
        if ($this->playerAccountId === null) {
            return null;
        }

        if (!array_key_exists($groupId, $this->trophyGroupPlayers)) {
            $this->trophyGroupPlayers[$groupId] = $this->gameService->getTrophyGroupPlayer(
                $this->game['np_communication_id'],
                $groupId,
                $this->playerAccountId
            );
        }

        return $this->trophyGroupPlayers[$groupId];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTrophies(string $groupId): array
    {
        if (!array_key_exists($groupId, $this->trophiesByGroup)) {
            $this->trophiesByGroup[$groupId] = $this->gameService->getTrophies(
                $this->game['np_communication_id'],
                $groupId,
                $this->playerAccountId,
                $this->sort
            );
        }

        return $this->trophiesByGroup[$groupId];
    }

    public function createMetaData(): PageMetaData
    {
        return (new PageMetaData())
            ->setTitle($this->game['name'] . ' Trophies')
            ->setDescription(
                $this->game['bronze'] . ' Bronze ~ '
                . $this->game['silver'] . ' Silver ~ '
                . $this->game['gold'] . ' Gold ~ '
                . $this->game['platinum'] . ' Platinum'
            )
            ->setImage('https://psn100.net/img/title/' . $this->game['icon_url'])
            ->setUrl('https://psn100.net/game/' . $this->game['id'] . '-' . $this->getGameSlug());
    }

    public function getPageTitle(): string
    {
        return $this->game['name'] . ' Trophies ~ PSN 100%';
    }

    public function getGameSlug(): string
    {
        return $this->utility->slugify((string) $this->game['name']);
    }
}
