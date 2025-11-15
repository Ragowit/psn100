<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPageContext.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPagePlayer.php';
require_once __DIR__ . '/../wwwroot/classes/AboutPageScanSummary.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class AboutPageDataProviderStub implements AboutPageDataProviderInterface
{
    private AboutPageScanSummary $summary;

    /**
     * @var list<AboutPagePlayer>
     */
    private array $players;

    /**
     * @param list<AboutPagePlayer> $players
     */
    public function __construct(AboutPageScanSummary $summary, array $players)
    {
        $this->summary = $summary;
        $this->players = $players;
    }

    public function getScanSummary(): AboutPageScanSummary
    {
        return $this->summary;
    }

    public function getScanLogPlayers(int $limit): array
    {
        return array_slice($this->players, 0, $limit);
    }
}

final class AboutPageContextTest extends TestCase
{
    public function testCreateBuildsInitialPlayerListUsingLimits(): void
    {
        $summary = new AboutPageScanSummary(100, 25);
        $players = $this->createPlayers(['Alice', 'Bob', 'Carol']);
        $dataProvider = new AboutPageDataProviderStub($summary, $players);

        $context = AboutPageContext::create($dataProvider, 2, 1, 'Custom Title');

        $this->assertSame($summary, $context->getScanSummary());
        $this->assertSame('Custom Title', $context->getTitle());
        $this->assertSame(2, $context->getScanLogLimit());
        $this->assertSame(1, $context->getMaxInitialDisplayCount());
        $this->assertSame(1, $context->getInitialDisplayCount());
        $this->assertCount(1, $context->getInitialScanLogPlayers());
        $this->assertSame('Alice', $context->getInitialScanLogPlayers()[0]->getOnlineId());
    }

    public function testGetScanLogPlayersDataSerializesPlayers(): void
    {
        $summary = new AboutPageScanSummary(10, 5);
        $players = $this->createPlayers(['Alpha', 'Beta']);
        $dataProvider = new AboutPageDataProviderStub($summary, $players);

        $context = AboutPageContext::create($dataProvider, 5, 5);
        $serializedPlayers = $context->getScanLogPlayersData();

        $this->assertCount(2, $serializedPlayers);
        $this->assertSame('Alpha', $serializedPlayers[0]['onlineId']);
        $this->assertSame('Beta', $serializedPlayers[1]['onlineId']);
    }

    /**
     * @param list<string> $names
     * @return list<AboutPagePlayer>
     */
    private function createPlayers(array $names): array
    {
        $utility = new Utility();
        $players = [];

        foreach ($names as $name) {
            $players[] = new AboutPagePlayer(
                $utility,
                $name,
                'SE',
                'avatar.png',
                '2024-01-01 00:00:00',
                100,
                '50',
                0,
                0,
                1,
                1,
                1
            );
        }

        return $players;
    }
}
