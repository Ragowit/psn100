<?php

declare(strict_types=1);

require_once __DIR__ . '/PageMetaData.php';
require_once __DIR__ . '/TrophyService.php';
require_once __DIR__ . '/TrophyRarityFormatter.php';
require_once __DIR__ . '/TrophyNotFoundException.php';
require_once __DIR__ . '/TrophyPlayerNotFoundException.php';
require_once __DIR__ . '/Utility.php';

class TrophyPage
{
    /**
     * @var array<string, mixed>
     */
    private array $trophy;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $playerTrophy;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $firstAchievers;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $latestAchievers;

    private ?int $playerAccountId;

    private ?string $playerOnlineId;

    private PageMetaData $metaData;

    private string $pageTitle;

    private TrophyRarity $trophyRarity;

    /**
     * @param array<string, mixed> $trophy
     * @param array<string, mixed>|null $playerTrophy
     * @param array<int, array<string, mixed>> $firstAchievers
     * @param array<int, array<string, mixed>> $latestAchievers
     */
    private function __construct(
        array $trophy,
        ?array $playerTrophy,
        array $firstAchievers,
        array $latestAchievers,
        ?int $playerAccountId,
        ?string $playerOnlineId,
        PageMetaData $metaData,
        string $pageTitle,
        TrophyRarity $trophyRarity
    ) {
        $this->trophy = $trophy;
        $this->playerTrophy = $playerTrophy;
        $this->firstAchievers = $firstAchievers;
        $this->latestAchievers = $latestAchievers;
        $this->playerAccountId = $playerAccountId;
        $this->playerOnlineId = $playerOnlineId;
        $this->metaData = $metaData;
        $this->pageTitle = $pageTitle;
        $this->trophyRarity = $trophyRarity;
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

        $trophyName = (string) $trophy['trophy_name'];
        $metaData = (new PageMetaData())
            ->setTitle($trophyName . ' Trophy')
            ->setDescription(htmlentities((string) $trophy['trophy_detail'], ENT_QUOTES, 'UTF-8'))
            ->setImage('https://psn100.net/img/trophy/' . $trophy['trophy_icon'])
            ->setUrl('https://psn100.net/trophy/' . $trophy['trophy_id'] . '-' . $utility->slugify($trophyName));

        $pageTitle = $trophyName . ' Trophy ~ PSN 100%';
        $trophyRarity = $rarityFormatter->format($trophy['rarity_percent'], (int) $trophy['status']);

        $playerAccountId = null;
        $playerOnlineId = null;
        $playerTrophy = null;

        $player = self::sanitizePlayer($player);
        if ($player !== null) {
            $playerAccountId = $trophyService->getPlayerAccountId($player);

            if ($playerAccountId === null) {
                throw new TrophyPlayerNotFoundException((string) $trophy['trophy_id'], $trophyName);
            }

            $playerOnlineId = $player;
            $progressTargetValue = $trophy['progress_target_value'] ?? null;
            if ($progressTargetValue !== null) {
                $progressTargetValue = (string) $progressTargetValue;
            }

            $playerTrophy = $trophyService->getPlayerTrophy(
                $playerAccountId,
                (string) $trophy['np_communication_id'],
                (int) $trophy['order_id'],
                $progressTargetValue
            );
        }

        $npCommunicationId = (string) $trophy['np_communication_id'];
        $orderId = (int) $trophy['order_id'];

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
            $trophyRarity
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrophy(): array
    {
        return $this->trophy;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPlayerTrophy(): ?array
    {
        return $this->playerTrophy;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFirstAchievers(): array
    {
        return $this->firstAchievers;
    }

    /**
     * @return array<int, array<string, mixed>>
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

    public function getTrophyRarity(): TrophyRarity
    {
        return $this->trophyRarity;
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
