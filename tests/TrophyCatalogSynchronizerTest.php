<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyCatalogSynchronizer.php';

final class TrophyCatalogSynchronizerTest extends TestCase
{
    private PDO $database;
    private TrophyCatalogSynchronizer $synchronizer;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec(
            'CREATE TABLE trophy_title (
                np_communication_id TEXT PRIMARY KEY,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                platform TEXT,
                set_version TEXT
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy_group (
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                PRIMARY KEY (np_communication_id, group_id)
            )'
        );
        $this->database->exec(
            'CREATE TABLE trophy (
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL,
                order_id INTEGER NOT NULL,
                hidden INTEGER,
                type TEXT,
                name TEXT,
                detail TEXT,
                icon_url TEXT,
                progress_target_value INTEGER,
                reward_name TEXT,
                reward_image_url TEXT,
                PRIMARY KEY (np_communication_id, group_id, order_id)
            )'
        );

        $this->synchronizer = new TrophyCatalogSynchronizer($this->database);
    }

    public function testFetchExistingTrophyTitleInfoReturnsDefaultsWhenMissing(): void
    {
        $result = $this->synchronizer->fetchExistingTrophyTitleInfo('NPWR00001_00');

        $this->assertSame(null, $result['detail']);
        $this->assertSame(null, $result['icon']);
        $this->assertSame('', $result['platform']);
        $this->assertSame([], $result['platforms']);
        $this->assertSame(null, $result['set_version']);
    }

    public function testFetchExistingTrophyTitleInfoParsesPlatformList(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_title (np_communication_id, detail, icon_url, platform, set_version)
            VALUES ('NPWR00001_00', 'Details', 'icon.png', 'PS5, PS4', '01.00')"
        );

        $result = $this->synchronizer->fetchExistingTrophyTitleInfo('NPWR00001_00');

        $this->assertSame('Details', $result['detail']);
        $this->assertSame('icon.png', $result['icon']);
        $this->assertSame('PS5, PS4', $result['platform']);
        $this->assertSame(['PS5', 'PS4'], $result['platforms']);
        $this->assertSame('01.00', $result['set_version']);
    }

    public function testFetchExistingTrophyTitleRowReturnsNullWhenMissing(): void
    {
        $this->assertSame(null, $this->synchronizer->fetchExistingTrophyTitleRow('NPWR00001_00'));
    }

    public function testFetchExistingTrophyGroupDataIndexesByGroupId(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_group (np_communication_id, group_id, name, detail, icon_url) VALUES
                ('NPWR00001_00', 'default', 'Base Game', 'Base detail', 'base.png'),
                ('NPWR00001_00', '001', 'DLC', 'DLC detail', 'dlc.png')"
        );

        $groups = $this->synchronizer->fetchExistingTrophyGroupData('NPWR00001_00');

        $this->assertSame('Base Game', $groups['default']['name']);
        $this->assertSame('DLC detail', $groups['001']['detail']);
        $this->assertSame('dlc.png', $groups['001']['icon']);
    }

    public function testFetchExistingTrophyGroupReturnsSingleRow(): void
    {
        $this->database->exec(
            "INSERT INTO trophy_group (np_communication_id, group_id, name, detail, icon_url)
            VALUES ('NPWR00001_00', 'default', 'Base Game', 'Base detail', 'base.png')"
        );

        $group = $this->synchronizer->fetchExistingTrophyGroup('NPWR00001_00', 'default');

        $this->assertSame('Base Game', $group['name']);
        $this->assertSame('base.png', $group['icon_url']);
    }

    public function testFetchExistingTrophyDataIndexesByGroupAndOrderId(): void
    {
        $this->database->exec(
            "INSERT INTO trophy (
                np_communication_id, group_id, order_id, hidden, type, name, detail, icon_url,
                progress_target_value, reward_name, reward_image_url
            ) VALUES
                ('NPWR00001_00', 'default', 1, 0, 'bronze', 'Trophy One', 'Detail', 'one.png', NULL, NULL, NULL),
                ('NPWR00001_00', 'default', 2, 1, 'gold', 'Trophy Two', 'Detail 2', 'two.png', 10, 'Reward', 'reward.png')"
        );

        $trophies = $this->synchronizer->fetchExistingTrophyData('NPWR00001_00');

        $this->assertSame('0', $trophies['default'][1]['hidden']);
        $this->assertSame('gold', $trophies['default'][2]['type']);
        $this->assertSame('10', $trophies['default'][2]['progress_target_value']);
        $this->assertSame('reward.png', $trophies['default'][2]['reward_image']);
    }

    public function testFetchExistingTrophyReturnsSingleRow(): void
    {
        $this->database->exec(
            "INSERT INTO trophy (
                np_communication_id, group_id, order_id, hidden, type, name, detail, icon_url,
                progress_target_value, reward_name, reward_image_url
            ) VALUES ('NPWR00001_00', 'default', 3, 0, 'silver', 'Trophy Three', 'Detail', 'three.png', NULL, NULL, NULL)"
        );

        $trophy = $this->synchronizer->fetchExistingTrophy('NPWR00001_00', 'default', 3);

        $this->assertSame('silver', $trophy['type']);
        $this->assertSame('three.png', $trophy['icon_url']);
    }

    public function testUpsertTrophyTitleMethodAcceptsCatalogFields(): void
    {
        $method = new ReflectionMethod(TrophyCatalogSynchronizer::class, 'upsertTrophyTitle');

        $this->assertSame(7, $method->getNumberOfParameters());
        $this->assertSame('int', (string) $method->getReturnType());
    }
}
