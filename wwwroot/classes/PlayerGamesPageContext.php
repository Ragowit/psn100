<?php

declare(strict_types=1);

require_once __DIR__ . '/PageMetaData.php';
require_once __DIR__ . '/PlayerGamesFilter.php';
require_once __DIR__ . '/PlayerGamesPage.php';
require_once __DIR__ . '/PlayerGamesService.php';
require_once __DIR__ . '/PlayerSummary.php';
require_once __DIR__ . '/PlayerSummaryService.php';

final class PlayerGamesPageContext
{
    private PlayerGamesPage $playerGamesPage;

    private PlayerSummary $playerSummary;

    private PlayerGamesFilter $filter;

    private PageMetaData $metaData;

    private string $title;

    private string $playerSearch;

    private string $sort;

    /**
     * @param array<string, mixed> $playerData
     */
    private function __construct(
        PlayerGamesPage $playerGamesPage,
        PlayerSummary $playerSummary,
        PlayerGamesFilter $filter,
        array $playerData
    ) {
        $this->playerGamesPage = $playerGamesPage;
        $this->playerSummary = $playerSummary;
        $this->filter = $filter;
        $this->metaData = $this->buildMetaData($playerData, $playerSummary);
        $this->title = $this->buildTitle($playerData);
        $this->playerSearch = $filter->getSearch();
        $this->sort = $filter->getSort();
    }

    /**
     * @param array<string, mixed> $playerData
     * @param array<string, mixed> $queryParameters
     */
    public static function fromGlobals(
        PDO $database,
        array $playerData,
        int $accountId,
        array $queryParameters
    ): self {
        $playerSummaryService = new PlayerSummaryService($database);
        $playerSummary = $playerSummaryService->getSummary($accountId);

        $filter = PlayerGamesFilter::fromArray($queryParameters);
        $playerGamesService = new PlayerGamesService($database);
        $playerGamesPage = new PlayerGamesPage(
            $playerGamesService,
            $filter,
            $accountId,
            self::extractPlayerStatus($playerData)
        );

        return new self($playerGamesPage, $playerSummary, $filter, $playerData);
    }

    public function getPlayerSummary(): PlayerSummary
    {
        return $this->playerSummary;
    }

    public function getNumberOfGames(): int
    {
        return $this->playerSummary->getNumberOfGames();
    }

    public function getFilter(): PlayerGamesFilter
    {
        return $this->filter;
    }

    public function getPlayerGamesPage(): PlayerGamesPage
    {
        return $this->playerGamesPage;
    }

    /**
     * @return PlayerGame[]
     */
    public function getGames(): array
    {
        return $this->playerGamesPage->getGames();
    }

    public function getMetaData(): PageMetaData
    {
        return $this->metaData;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSearch(): string
    {
        return $this->playerSearch;
    }

    public function getSort(): string
    {
        return $this->sort;
    }

    /**
     * @param array<string, mixed> $playerData
     */
    private function buildMetaData(array $playerData, PlayerSummary $playerSummary): PageMetaData
    {
        $metaData = (new PageMetaData())
            ->setTitle($this->buildTitle($playerData))
            ->setImage('https://psn100.net/img/avatar/' . $this->extractString($playerData['avatar_url'] ?? ''))
            ->setUrl('https://psn100.net/player/' . $this->extractString($playerData['online_id'] ?? ''));

        $status = self::extractPlayerStatus($playerData);

        if ($status === 1) {
            return $metaData->setDescription('The player is flagged as a cheater.');
        }

        if ($status === 3) {
            return $metaData->setDescription('The player is private.');
        }

        $numberOfGames = $playerSummary->getNumberOfGames();
        $level = $this->extractInt($playerData['level'] ?? 0);
        $progress = $this->extractInt($playerData['progress'] ?? 0);
        $platinums = $this->extractInt($playerData['platinum'] ?? 0);

        $description = sprintf(
            'Level %d.%d ~ %d Unique Games ~ %d Unique Platinums',
            $level,
            $progress,
            $numberOfGames,
            $platinums
        );

        return $metaData->setDescription($description);
    }

    /**
     * @param array<string, mixed> $playerData
     */
    private function buildTitle(array $playerData): string
    {
        $onlineId = $this->extractString($playerData['online_id'] ?? '');

        return $onlineId . "'s Trophy Progress ~ PSN 100%";
    }

    /**
     * @param array<string, mixed> $playerData
     */
    private static function extractPlayerStatus(array $playerData): int
    {
        return (int) ($playerData['status'] ?? 0);
    }

    private function extractString(mixed $value): string
    {
        return (string) $value;
    }

    private function extractInt(mixed $value): int
    {
        return (int) $value;
    }
}
