<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanDifferenceTracker.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/GameRescanService.php';

final class GameRescanServiceSetVersionTest extends TestCase
{
    public function testIsSetVersionAtLeastCurrentReturnsFalseForLowerVersion(): void
    {
        $method = new ReflectionMethod(GameRescanService::class, 'isSetVersionAtLeastCurrent');
        $method->setAccessible(true);

        $result = $method->invoke(null, '01.09', '01.10');

        $this->assertFalse($result);
    }

    public function testIsSetVersionAtLeastCurrentAllowsEqualOrGreaterVersions(): void
    {
        $method = new ReflectionMethod(GameRescanService::class, 'isSetVersionAtLeastCurrent');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, '01.10', '01.10'));
        $this->assertTrue($method->invoke(null, '01.11', '01.10'));
    }

    public function testUpdateTrophySetVersionSkipsDowngrade(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE trophy_title (np_communication_id TEXT PRIMARY KEY, set_version TEXT)');
        $database->exec("INSERT INTO trophy_title (np_communication_id, set_version) VALUES ('NPWR00001_00', '01.10')");

        $service = new GameRescanService($database, new TrophyCalculator($database));
        $differenceTracker = new GameRescanDifferenceTracker();

        $method = new ReflectionMethod(GameRescanService::class, 'updateTrophySetVersion');
        $method->setAccessible(true);
        $method->invoke($service, 'NPWR00001_00', '01.09', $differenceTracker);

        $updatedVersion = $database->query(
            "SELECT set_version FROM trophy_title WHERE np_communication_id = 'NPWR00001_00'"
        )->fetchColumn();

        $this->assertSame('01.10', $updatedVersion);
        $this->assertSame([], $differenceTracker->getDifferences());
    }
}
