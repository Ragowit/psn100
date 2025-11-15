<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Game/GameTrophyGroup.php';

final class GameTrophyGroupTest extends TestCase
{
    private function createGroup(array $data = [], bool $usesPlayStation5Assets = false): GameTrophyGroup
    {
        $defaults = [
            'group_id' => 'default',
            'name' => 'Base Game',
            'detail' => 'Contains the core trophies.',
            'icon_url' => 'group.png',
            'bronze' => 1,
            'silver' => 2,
            'gold' => 3,
            'platinum' => 4,
        ];

        return GameTrophyGroup::fromArray($data + $defaults, $usesPlayStation5Assets);
    }

    public function testGetIconPathUsesConsoleSpecificPlaceholder(): void
    {
        $ps5Group = $this->createGroup(['icon_url' => '.png'], true);
        $ps4Group = $this->createGroup(['icon_url' => '.png'], false);

        $this->assertSame('../missing-ps5-game-and-trophy.png', $ps5Group->getIconPath());
        $this->assertSame('../missing-ps4-game.png', $ps4Group->getIconPath());
    }

    public function testIsDefaultGroupMatchesGroupId(): void
    {
        $defaultGroup = $this->createGroup(['group_id' => 'default']);
        $dlcGroup = $this->createGroup(['group_id' => '100']);

        $this->assertTrue($defaultGroup->isDefaultGroup());
        $this->assertFalse($dlcGroup->isDefaultGroup());
    }

    public function testGettersExposeTrophyCounts(): void
    {
        $group = $this->createGroup([
            'bronze' => '12',
            'silver' => '5',
            'gold' => '2',
            'platinum' => '1',
        ]);

        $this->assertSame(12, $group->getBronzeCount());
        $this->assertSame(5, $group->getSilverCount());
        $this->assertSame(2, $group->getGoldCount());
        $this->assertSame(1, $group->getPlatinumCount());
    }
}
