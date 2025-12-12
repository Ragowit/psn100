<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerRandomGamesService.php';
require_once __DIR__ . '/PlayerRandomGamesFilter.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';

class PlayerRandomGamesPage
{
    private const int STATUS_FLAGGED = 1;
    private const int STATUS_PRIVATE = 3;

    private PlayerRandomGamesFilter $filter;

    private PlayerSummary $playerSummary;

    /**
     * @var PlayerRandomGame[]
     */
    private array $randomGames;

    private int $playerStatus;

    public function __construct(
        PlayerRandomGamesService $randomGamesService,
        PlayerSummaryService $summaryService,
        PlayerRandomGamesFilter $filter,
        int $accountId,
        int $playerStatus
    ) {
        $this->filter = $filter;
        $this->playerSummary = $summaryService->getSummary($accountId);
        $this->playerStatus = $playerStatus;

        if ($this->shouldLoadRandomGames()) {
            $this->randomGames = $randomGamesService->getRandomGames($accountId, $filter);
        } else {
            $this->randomGames = [];
        }
    }

    public function getFilter(): PlayerRandomGamesFilter
    {
        return $this->filter;
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    /**
     * @return PlayerRandomGame[]
     */
    public function getRandomGames(): array
    {
        return $this->randomGames;
    }

    public function shouldShowFlaggedMessage(): bool
    {
        return $this->playerStatus === self::STATUS_FLAGGED;
    }

    public function shouldShowPrivateMessage(): bool
    {
        return $this->playerStatus === self::STATUS_PRIVATE;
    }

    public function shouldShowRandomGames(): bool
    {
        return !$this->shouldShowFlaggedMessage() && !$this->shouldShowPrivateMessage();
    }

    private function shouldLoadRandomGames(): bool
    {
        return $this->shouldShowRandomGames();
    }
}
