<?php

declare(strict_types=1);

require_once __DIR__ . '/Html.php';

require_once __DIR__ . '/PageMetaData.php';
require_once __DIR__ . '/TrophyService.php';
require_once __DIR__ . '/TrophyRarityFormatter.php';
require_once __DIR__ . '/TrophyNotFoundException.php';
require_once __DIR__ . '/TrophyPlayerNotFoundException.php';
require_once __DIR__ . '/Utility.php';
require_once __DIR__ . '/TrophyDetails.php';
require_once __DIR__ . '/PlayerTrophyProgress.php';
require_once __DIR__ . '/TrophyAchiever.php';

final readonly class TrophyPage
{
    /**
     * @param list<TrophyAchiever> $firstAchievers
     * @param list<TrophyAchiever> $latestAchievers
     */
    private function __construct(
        final private TrophyDetails $trophy,
        final private ?PlayerTrophyProgress $playerTrophy,
        final private array $firstAchievers,
        final private array $latestAchievers,
        final private ?int $playerAccountId,
        final private ?string $playerOnlineId,
        final private PageMetaData $metaData,
        final private string $pageTitle,
        final private TrophyRarity $metaRarity,
        final private TrophyRarity $inGameRarity,
    ) {
    }

    #[\NoDiscard]
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
            ->withDescription(Html::escape($trophy->getDetail()))
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
