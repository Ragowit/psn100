<?php

declare(strict_types=1);

require_once __DIR__ . '/GameService.php';
require_once __DIR__ . '/GameHistoryService.php';
require_once __DIR__ . '/GameHistoryChangeFilter.php';
require_once __DIR__ . '/GameHeaderService.php';
require_once __DIR__ . '/GameNotFoundException.php';
require_once __DIR__ . '/Game/GameDetails.php';
require_once __DIR__ . '/Game/GameHeaderData.php';
require_once __DIR__ . '/PageMetaData.php';
require_once __DIR__ . '/Utility.php';

final class GameHistoryPage
{
    private GameService $gameService;

    private GameHistoryService $historyService;

    private GameHeaderService $gameHeaderService;

    private GameHistoryChangeFilter $historyChangeFilter;

    private Utility $utility;

    private GameDetails $game;

    private GameHeaderData $headerData;

    /**
     * @var array<int, array{
     *     historyId: int,
     *     discoveredAt: DateTimeImmutable,
     *     title: ?array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     titleHighlights: array{detail: bool, icon_url: bool, set_version: bool},
     *     titleFieldDiffs?: array<string, array{previous: mixed, current: mixed}>,
     *     hasTitleChanges: bool,
     *     isTitleNewRow: bool,
     *     groups: array<int, array{
     *         group_id: string,
     *         name: ?string,
     *         detail: ?string,
     *         icon_url: ?string,
     *         changedFields: array{name: bool, detail: bool, icon_url: bool},
     *         fieldDiffs?: array<string, array{previous: mixed, current: mixed}>,
     *         isNewRow: bool
     *     }>,
     *     trophies: array<int, array{
     *         group_id: string,
     *         order_id: int,
     *         name: ?string,
     *         detail: ?string,
     *         icon_url: ?string,
     *         progress_target_value: ?int,
     *         is_unobtainable: bool,
     *         changedFields: array{name: bool, detail: bool, icon_url: bool, progress_target_value: bool},
     *         fieldDiffs?: array<string, array{previous: mixed, current: mixed}>,
     *         isNewRow: bool
     *     }>
     * }>|null
     */
    private ?array $historyEntries = null;

    public function __construct(
        GameService $gameService,
        GameHistoryService $historyService,
        GameHeaderService $gameHeaderService,
        Utility $utility,
        int $gameId,
        ?GameHistoryChangeFilter $historyChangeFilter = null
    ) {
        $this->gameService = $gameService;
        $this->historyService = $historyService;
        $this->gameHeaderService = $gameHeaderService;
        $this->historyChangeFilter = $historyChangeFilter ?? new GameHistoryChangeFilter();
        $this->utility = $utility;

        $this->game = $this->loadGame($gameId);
        $this->headerData = $this->gameHeaderService->buildHeaderData($this->game);
    }

    private function loadGame(int $gameId): GameDetails
    {
        $game = $this->gameService->getGame($gameId);

        if ($game === null) {
            throw new GameNotFoundException('Game not found: ' . $gameId);
        }

        return $game;
    }

    public function getGame(): GameDetails
    {
        return $this->game;
    }

    public function getGameHeaderData(): GameHeaderData
    {
        return $this->headerData;
    }

    /**
     * @return array<int, array{
     *     historyId: int,
     *     discoveredAt: DateTimeImmutable,
     *     title: ?array{detail: ?string, icon_url: ?string, set_version: ?string},
     *     groups: array<int, array{group_id: string, name: ?string, detail: ?string, icon_url: ?string}>,
     *     trophies: array<int, array{group_id: string, order_id: int, name: ?string, detail: ?string, icon_url: ?string, progress_target_value: ?int, is_unobtainable: bool}>
     * }>
     */
    public function getHistoryEntries(): array
    {
        if ($this->historyEntries === null) {
            $history = $this->historyService->getHistoryForGame($this->game->getId());
            $this->historyEntries = $this->historyChangeFilter->filter($history);
        }

        return $this->historyEntries;
    }

    public function createMetaData(): PageMetaData
    {
        return (new PageMetaData())
            ->withTitle($this->game->getName() . ' Trophy Data History')
            ->withDescription('Version history and trophy data changes for ' . $this->game->getName())
            ->withImage('https://psn100.net/img/title/' . $this->game->getIconUrl())
            ->withUrl('https://psn100.net/game-history/' . $this->game->getId() . '-' . $this->getGameSlug());
    }

    public function getPageTitle(): string
    {
        return $this->game->getName() . ' Trophy Data History ~ PSN 100%';
    }

    public function getGameSlug(): string
    {
        return $this->utility->slugify($this->game->getName());
    }
}
