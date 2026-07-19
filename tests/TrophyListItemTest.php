<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyListItem.php';

final class TrophyListItemTest extends TestCase
{
    public function testFromArrayUsesTrophyTypeEnum(): void
    {
        $item = TrophyListItem::fromArray([
            'trophy_id' => 7,
            'trophy_type' => 'GOLD',
            'trophy_name' => 'Gold Trophy',
            'trophy_detail' => 'Earn gold',
            'trophy_icon' => 'gold.png',
            'rarity_percent' => 3.5,
            'in_game_rarity_percent' => 4.0,
            'game_id' => 11,
            'game_name' => 'Example Game',
            'game_icon' => 'game.png',
            'platform' => 'PS5',
        ]);

        $this->assertSame(TrophyType::Gold, $item->getTrophyType());
        $this->assertSame('/img/trophy-gold.svg', $item->getTrophyType()->iconPath());
        $this->assertSame('Gold', $item->getTrophyType()->label());
    }
}
