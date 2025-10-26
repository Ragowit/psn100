<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/GameLeaderboardRow.php';
require_once __DIR__ . '/../wwwroot/classes/GamePlayerFilter.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class GameLeaderboardRowTest extends TestCase
{
    public function testFromArrayCastsValuesAndProvidesAccessors(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'account_id' => 'abc123',
            'avatar_url' => 'https://example.com/avatar.png',
            'country' => 'US',
            'name' => 'PlayerOne',
            'trophy_count_npwr' => '120',
            'trophy_count_sony' => '150',
            'bronze' => '30',
            'silver' => '40',
            'gold' => '20',
            'platinum' => '5',
            'progress' => '87',
            'last_known_date' => '2023-05-01',
        ]);

        $utility = new class extends Utility {
            public ?string $receivedCountryCode = null;

            public function getCountryName(?string $countryCode): string
            {
                $this->receivedCountryCode = $countryCode;

                return 'United States';
            }
        };

        $this->assertTrue($row->matchesAccountId('abc123'));
        $this->assertFalse($row->matchesAccountId(null));
        $this->assertFalse($row->matchesAccountId('different'));
        $this->assertSame('https://example.com/avatar.png', $row->getAvatarUrl());
        $this->assertSame('US', $row->getCountryCode());
        $this->assertSame('United States', $row->getCountryName($utility));
        $this->assertSame('US', $utility->receivedCountryCode);
        $this->assertSame('PlayerOne', $row->getOnlineId());
        $this->assertSame(30, $row->getBronzeCount());
        $this->assertSame(40, $row->getSilverCount());
        $this->assertSame(20, $row->getGoldCount());
        $this->assertSame(5, $row->getPlatinumCount());
        $this->assertSame(87, $row->getProgress());
        $this->assertSame('2023-05-01', $row->getLastKnownDate());
        $this->assertTrue($row->hasHiddenTrophies());
    }

    public function testFromArrayDefaultsMissingValues(): void
    {
        $row = GameLeaderboardRow::fromArray([]);

        $this->assertFalse($row->matchesAccountId('anything'));
        $this->assertSame('', $row->getAvatarUrl());
        $this->assertSame('', $row->getCountryCode());
        $this->assertSame('', $row->getOnlineId());
        $this->assertSame(0, $row->getBronzeCount());
        $this->assertSame(0, $row->getSilverCount());
        $this->assertSame(0, $row->getGoldCount());
        $this->assertSame(0, $row->getPlatinumCount());
        $this->assertSame(0, $row->getProgress());
        $this->assertSame('', $row->getLastKnownDate());
        $this->assertFalse($row->hasHiddenTrophies());
    }

    public function testQueryParameterHelpersMergeWithFilter(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'avatar_url' => '  https://cdn.example.com/avatar.jpg  ',
            'country' => '  ca  ',
        ]);

        $filter = new GamePlayerFilter('DE', 'current-avatar');

        $this->assertSame(
            [
                'country' => 'DE',
                'avatar' => 'https://cdn.example.com/avatar.jpg',
            ],
            $row->getAvatarQueryParameters($filter)
        );

        $this->assertSame(
            [
                'country' => 'ca',
                'avatar' => 'current-avatar',
            ],
            $row->getCountryQueryParameters($filter)
        );
    }

    public function testQueryParameterHelpersRemoveEmptyValues(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'avatar_url' => '   ',
            'country' => '',
        ]);

        $filter = new GamePlayerFilter('BR', 'avatar-id');

        $this->assertSame(
            ['country' => 'BR'],
            $row->getAvatarQueryParameters($filter)
        );

        $this->assertSame(
            ['avatar' => 'avatar-id'],
            $row->getCountryQueryParameters($filter)
        );
    }
}
