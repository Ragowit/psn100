<?php

declare(strict_types=1);

require_once __DIR__ . '/AboutPageDataProviderInterface.php';
require_once __DIR__ . '/AboutPagePlayerArraySerializer.php';

final readonly class AboutPageContext
{
    private const DEFAULT_SCAN_LOG_LIMIT = 30;
    private const DEFAULT_MAX_INITIAL_DISPLAY_COUNT = 10;
    private const DEFAULT_TITLE = 'About ~ PSN 100%';

    /**
     * @param list<AboutPagePlayer> $scanLogPlayers
     * @param list<AboutPagePlayer> $initialScanLogPlayers
     * @param list<array<string, mixed>> $serializedScanLogPlayers
     */
    private function __construct(
        private AboutPageScanSummary $scanSummary,
        private array $scanLogPlayers,
        private array $initialScanLogPlayers,
        private array $serializedScanLogPlayers,
        private int $scanLogLimit,
        private int $initialDisplayCount,
        private int $maxInitialDisplayCount,
        private string $title,
    ) {
    }

    public static function create(
        AboutPageDataProviderInterface $dataProvider,
        int $scanLogLimit = self::DEFAULT_SCAN_LOG_LIMIT,
        int $maxInitialDisplayCount = self::DEFAULT_MAX_INITIAL_DISPLAY_COUNT,
        string $title = self::DEFAULT_TITLE
    ): self {
        $scanSummary = $dataProvider->getScanSummary();
        $scanLogPlayers = $dataProvider->getScanLogPlayers($scanLogLimit);
        $initialDisplayCount = min($maxInitialDisplayCount, count($scanLogPlayers));
        $initialScanLogPlayers = array_slice($scanLogPlayers, 0, $initialDisplayCount);
        $serializedScanLogPlayers = AboutPagePlayerArraySerializer::serializeCollection($scanLogPlayers);

        return new self(
            $scanSummary,
            $scanLogPlayers,
            $initialScanLogPlayers,
            $serializedScanLogPlayers,
            $scanLogLimit,
            $initialDisplayCount,
            $maxInitialDisplayCount,
            $title
        );
    }

    public function getScanSummary(): AboutPageScanSummary
    {
        return $this->scanSummary;
    }

    /**
     * @return list<AboutPagePlayer>
     */
    public function getInitialScanLogPlayers(): array
    {
        return $this->initialScanLogPlayers;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getScanLogPlayersData(): array
    {
        return $this->serializedScanLogPlayers;
    }

    public function getInitialDisplayCount(): int
    {
        return $this->initialDisplayCount;
    }

    public function getMaxInitialDisplayCount(): int
    {
        return $this->maxInitialDisplayCount;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getScanLogLimit(): int
    {
        return $this->scanLogLimit;
    }
}
