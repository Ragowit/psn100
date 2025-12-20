<?php

declare(strict_types=1);

require_once __DIR__ . '/PageMetaData.php';
require_once __DIR__ . '/TrophyService.php';
require_once __DIR__ . '/TrophyRarityFormatter.php';
require_once __DIR__ . '/TrophyNotFoundException.php';
require_once __DIR__ . '/TrophyPlayerNotFoundException.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/TrophyDetails.php';
require_once __DIR__ . '/PlayerTrophyProgress.php';
require_once __DIR__ . '/TrophyAchiever.php';

class TrophyPage
{
    private TrophyDetails $trophy;

    private ?PlayerTrophyProgress $playerTrophy;

    /**
     * @var list<TrophyAchiever>
     */
    private array $firstAchievers;

    /**
     * @var list<TrophyAchiever>
     */
    private array $latestAchievers;

    private ?int $playerAccountId;

    private ?string $playerOnlineId;

    private PageMetaData $metaData;

    private string $pageTitle;

    private TrophyRarity $metaRarity;

    private TrophyRarity $inGameRarity;

    /**
     * @param list<TrophyAchiever> $firstAchievers
     * @param list<TrophyAchiever> $latestAchievers
     */
    private function __construct(
        TrophyDetails $trophy,
        ?PlayerTrophyProgress $playerTrophy,
        array $firstAchievers,
        array $latestAchievers,
        ?int $playerAccountId,
        ?string $playerOnlineId,
        PageMetaData $metaData,
        string $pageTitle,
        TrophyRarity $metaRarity,
        TrophyRarity $inGameRarity
    ) {
        $this->trophy = $trophy;
        $this->playerTrophy = $playerTrophy;
        $this->firstAchievers = $firstAchievers;
        $this->latestAchievers = $latestAchievers;
        $this->playerAccountId = $playerAccountId;
        $this->playerOnlineId = $playerOnlineId;
        $this->metaData = $metaData;
        $this->pageTitle = $pageTitle;
        $this->metaRarity = $metaRarity;
        $this->inGameRarity = $inGameRarity;
    }

    public static function create(
        TrophyService $trophyService,
        Utility $utility,
        TrophyRarityFormatter $rarityFormatter,
        int $trophyId,
        ?string $player
    ): self {
        $trophy = $trophyService->getTrophyById($trophyId);
        if ($trophy === null) {
            throw new TrophyNotFoundException('Trophy not found.');
        }

        $trophyName = $trophy->getName();
        $metaData = (new PageMetaData())
            ->withTitle($trophyName . ' Trophy')
            ->withDescription(htmlentities($trophy->getDetail(), ENT_QUOTES, 'UTF-8'))
            ->withImage('https://psn100.net/img/trophy/' . $trophy->getIconFileName())
            ->withUrl('https://psn100.net/trophy/' . $trophy->getTrophySlug($utility));

        $pageTitle = $trophyName . ' Trophy ~ PSN 100%';
        $metaRarity = $rarityFormatter->formatMeta($trophy->getRarityPercent(), $trophy->getStatus());
        $inGameRarity = $rarityFormatter->formatInGame($trophy->getInGameRarityPercent(), $trophy->getStatus());

        $playerAccountId = null;
        $playerOnlineId = null;
        $playerTrophy = null;

        $player = self::sanitizePlayer($player);
        if ($player !== null) {
            $playerAccountId = $trophyService->getPlayerAccountId($player);

            if ($playerAccountId === null) {
                throw new TrophyPlayerNotFoundException((string) $trophy->getId(), $trophyName);
            }

            $playerOnlineId = $player;
            $playerTrophy = $trophyService->getPlayerTrophy(
                $playerAccountId,
                $trophy->getNpCommunicationId(),
                $trophy->getOrderId(),
                $trophy->getProgressTargetValue()
            );
        }

        $npCommunicationId = $trophy->getNpCommunicationId();
        $orderId = $trophy->getOrderId();

        $firstAchievers = $trophyService->getFirstAchievers($npCommunicationId, $orderId);
        $latestAchievers = $trophyService->getLatestAchievers($npCommunicationId, $orderId);

        return new self(
            $trophy,
            $playerTrophy,
            $firstAchievers,
            $latestAchievers,
            $playerAccountId,
            $playerOnlineId,
            $metaData,
            $pageTitle,
            $metaRarity,
            $inGameRarity
        );
    }

    public function getTrophy(): TrophyDetails
    {
        return $this->trophy;
    }

    public function getPlayerTrophy(): ?PlayerTrophyProgress
    {
        return $this->playerTrophy;
    }

    /**
     * @return list<TrophyAchiever>
     */
    public function getFirstAchievers(): array
    {
        return $this->firstAchievers;
    }

    /**
     * @return list<TrophyAchiever>
     */
    public function getLatestAchievers(): array
    {
        return $this->latestAchievers;
    }

    public function getPlayerAccountId(): ?int
    {
        return $this->playerAccountId;
    }

    public function getPlayerOnlineId(): ?string
    {
        return $this->playerOnlineId;
    }

    public function getMetaData(): PageMetaData
    {
        return $this->metaData;
    }

    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }

    public function getMetaRarity(): TrophyRarity
    {
        return $this->metaRarity;
    }

    public function getInGameRarity(): TrophyRarity
    {
        return $this->inGameRarity;
    }

    private static function sanitizePlayer(?string $player): ?string
    {
        if ($player === null) {
            return null;
        }

        $player = trim($player);

        return $player === '' ? null : $player;
    }
}
