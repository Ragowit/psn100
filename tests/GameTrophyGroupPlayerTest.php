<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/Game/GameTrophyGroupPlayer.php';

final class GameTrophyGroupPlayerTest extends TestCase
{
    public function testFromArrayCastsValuesAndProvidesAccessors(): void
    {
        $player = GameTrophyGroupPlayer::fromArray([
            'np_communication_id' => 'NPWR12345',
            'group_id' => 'default',
            'account_id' => '42',
            'bronze' => '10',
            'silver' => '5',
            'gold' => '2',
            'platinum' => '1',
            'progress' => '75',
        ]);

        $this->assertSame('NPWR12345', $player->getNpCommunicationId());
        $this->assertSame('default', $player->getGroupId());
        $this->assertSame(42, $player->getAccountId());
        $this->assertSame(10, $player->getBronzeCount());
        $this->assertSame(5, $player->getSilverCount());
        $this->assertSame(2, $player->getGoldCount());
        $this->assertSame(1, $player->getPlatinumCount());
        $this->assertSame(75, $player->getProgress());
        $this->assertFalse($player->isComplete());
    }

    public function testIsCompleteReturnsTrueAtOrAboveHundredPercent(): void
    {
        $completePlayer = GameTrophyGroupPlayer::fromArray([
            'np_communication_id' => 'NPWR67890',
            'group_id' => '100',
            'account_id' => 7,
            'progress' => 100,
        ]);

        $overflowPlayer = GameTrophyGroupPlayer::fromArray([
            'np_communication_id' => 'NPWR67890',
            'group_id' => '100',
            'account_id' => 7,
            'progress' => 150,
        ]);

        $this->assertTrue($completePlayer->isComplete());
        $this->assertTrue($overflowPlayer->isComplete());
    }
}
