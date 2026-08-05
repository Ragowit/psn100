<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayerRandomGamesService.php';
require_once __DIR__ . '/PlayerRandomGamesFilter.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';
require_once __DIR__ . '/PlayerStatus.php';

final readonly class PlayerRandomGamesPage
{
    private PlayerSummary $playerSummary;

    /**
     * @var PlayerRandomGame[]
     */
    private array $randomGames;

    public function __construct(
        PlayerRandomGamesService $randomGamesService,
        PlayerSummaryService $summaryService,
        final private PlayerRandomGamesFilter $filter,
        int $accountId,
        final private PlayerStatus $playerStatus,
    ) {
        $this->playerSummary = $summaryService->getSummary($accountId);
        $this->randomGames = $this->shouldLoadRandomGames()
            ? $randomGamesService->getRandomGames($accountId, $filter)
            : [];
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
        return $this->playerStatus->isFlagged();
    }

    public function shouldShowPrivateMessage(): bool
    {
        return $this->playerStatus->isPrivateProfile();
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
