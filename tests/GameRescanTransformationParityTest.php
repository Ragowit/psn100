<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/PlayStationFixtureLoader.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanService.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Contracts/TrophyClientInterface.php';

final class GameRescanTransformationParityTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testRescanLookupTransformationParityAcrossServiceVariants(): void
    {
        $trophyFixture = PlayStationFixtureLoader::loadJson('trophies/groups-and-trophies-trophy.json');
        $trophy2Fixture = PlayStationFixtureLoader::loadJson('trophies/groups-and-trophies-trophy2.json');

        $service = $this->buildRescanService();
        $method = new ReflectionMethod(GameRescanService::class, 'fetchGameLookupGroupData');
        $method->setAccessible(true);

        $legacyGroups = $method->invoke(
            $service,
            new FixtureTrophyClient($trophyFixture),
            'NPWR12345_00'
        );
        $newGroups = $method->invoke(
            $service,
            new FixtureTrophyClient($trophy2Fixture),
            'NPWR12345_00'
        );

        $this->assertSame(count($legacyGroups), count($newGroups));
        $this->assertSame((string) $legacyGroups[0]->id(), (string) $newGroups[0]->id());
        $this->assertSame((string) $legacyGroups[0]->trophies()[0]->id(), (string) $newGroups[0]->trophies()[0]->id());
        $this->assertSame((string) $legacyGroups[0]->trophies()[0]->name(), (string) $newGroups[0]->trophies()[0]->name());
    }

    public function testRescanLookupTransformationNormalizesFieldsUsedByTrophyCalculations(): void
    {
        $partialFixture = PlayStationFixtureLoader::loadJson('malformed/trophy-groups-partial.json');

        $service = $this->buildRescanService();
        $method = new ReflectionMethod(GameRescanService::class, 'fetchGameLookupGroupData');
        $method->setAccessible(true);

        $groups = $method->invoke(
            $service,
            new FixtureTrophyClient($partialFixture),
            'NPWR12345_00'
        );

        $this->assertSame('dlc1', $groups[0]->id());
        $this->assertSame('', $groups[0]->name());

        $trophy = $groups[0]->trophies()[0];
        $this->assertTrue($trophy->hidden());
        $this->assertSame('silver', $trophy->type());
        $this->assertSame('15', $trophy->progressTargetValue());
        $this->assertSame('DLC Badge', $trophy->rewardName());
        $this->assertSame(null, $trophy->rewardImageUrl());
    }

    public function testFixtureContainsTrophyTitleListingShapeUsedForRankingMetadata(): void
    {
        $fixture = PlayStationFixtureLoader::loadJson('trophies/titles-listing.json');

        $this->assertTrue(isset($fixture['trophyTitles']) && is_array($fixture['trophyTitles']));
        $this->assertSame('NPWR77777_00', $fixture['trophyTitles'][0]['npCommunicationId']);
        $this->assertSame('PS5', $fixture['trophyTitles'][0]['trophyTitlePlatform']);
        $this->assertSame('01.00', $fixture['trophyTitles'][0]['trophySetVersion']);
    }

    private function buildRescanService(): GameRescanService
    {
        return new GameRescanService(
            $this->database,
            new TrophyCalculator($this->database)
        );
    }
}

final class FixtureTrophyClient implements TrophyClientInterface
{
    /** @param array<string, mixed> $fixture */
    public function __construct(private readonly array $fixture)
    {
    }

    public function requestTrophyEndpoint(string $path, array $query = [], array $headers = []): mixed
    {
        if (str_ends_with($path, '/all/trophies')) {
            return (object) ($this->fixture['allTrophies'] ?? []);
        }

        return (object) ($this->fixture['trophyGroups'] ?? []);
    }
}
