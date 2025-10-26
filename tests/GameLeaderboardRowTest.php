<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameLeaderboardRow.php';
require_once __DIR__ . '/../wwwroot/classes/GamePlayerFilter.php';
require_once __DIR__ . '/../wwwroot/classes/Utility.php';

final class GameLeaderboardRowTest extends TestCase
{
    public function testFromArrayCastsValuesAndProvidesAccessors(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'account_id' => 12345,
            'avatar_url' => 'https://example.test/avatar.png',
            'country' => 'us',
            'name' => 'Player-One',
            'trophy_count_npwr' => '100',
            'trophy_count_sony' => '105',
            'bronze' => '12',
            'silver' => '8',
            'gold' => '4',
            'platinum' => '1',
            'progress' => '87',
            'last_known_date' => '2024-05-18',
        ]);

        $this->assertTrue($row->matchesAccountId('12345'));
        $this->assertFalse($row->matchesAccountId('54321'));

        $this->assertSame('https://example.test/avatar.png', $row->getAvatarUrl());
        $this->assertSame('us', $row->getCountryCode());
        $this->assertSame('Player-One', $row->getOnlineId());
        $this->assertSame(12, $row->getBronzeCount());
        $this->assertSame(8, $row->getSilverCount());
        $this->assertSame(4, $row->getGoldCount());
        $this->assertSame(1, $row->getPlatinumCount());
        $this->assertSame(87, $row->getProgress());
        $this->assertSame('2024-05-18', $row->getLastKnownDate());
    }

    public function testFromArrayProvidesDefaultValuesWhenKeysMissing(): void
    {
        $row = GameLeaderboardRow::fromArray([]);

        $this->assertFalse($row->matchesAccountId(null));
        $this->assertSame('', $row->getAvatarUrl());
        $this->assertSame('', $row->getCountryCode());
        $this->assertSame('', $row->getOnlineId());
        $this->assertSame(0, $row->getBronzeCount());
        $this->assertSame(0, $row->getSilverCount());
        $this->assertSame(0, $row->getGoldCount());
        $this->assertSame(0, $row->getPlatinumCount());
        $this->assertSame(0, $row->getProgress());
        $this->assertSame('', $row->getLastKnownDate());
    }

    public function testHasHiddenTrophiesReturnsTrueWhenSonyCountHigher(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'account_id' => 'abc',
            'trophy_count_npwr' => 50,
            'trophy_count_sony' => 75,
        ]);

        $this->assertTrue($row->hasHiddenTrophies());
    }

    public function testHasHiddenTrophiesReturnsFalseWhenCountsEqual(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'account_id' => 'abc',
            'trophy_count_npwr' => 50,
            'trophy_count_sony' => 50,
        ]);

        $this->assertFalse($row->hasHiddenTrophies());
    }

    public function testGetCountryNameDelegatesToUtility(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'account_id' => 'abc',
            'country' => 'jp',
        ]);

        $utility = new class extends Utility {
            public ?string $receivedCode = null;

            public function getCountryName(?string $countryCode): string
            {
                $this->receivedCode = $countryCode;

                return 'Stubbed Name';
            }
        };

        $this->assertSame('Stubbed Name', $row->getCountryName($utility));
        $this->assertSame('jp', $utility->receivedCode);
    }

    public function testGetQueryParametersDelegateToFilter(): void
    {
        $row = GameLeaderboardRow::fromArray([
            'account_id' => 'abc',
            'avatar_url' => 'https://example.test/avatar.png',
            'country' => 'gb',
        ]);

        $filter = new class(null, null) extends GamePlayerFilter {
            public ?string $countryArgument = null;
            public ?string $avatarArgument = null;

            public function __construct(?string $country, ?string $avatar)
            {
                parent::__construct($country, $avatar);
            }

            public function withCountry(?string $country): array
            {
                $this->countryArgument = $country;

                return ['country' => 'filtered-' . (string) $country];
            }

            public function withAvatar(?string $avatar): array
            {
                $this->avatarArgument = $avatar;

                return ['avatar' => 'filtered-' . (string) $avatar];
            }
        };

        $this->assertSame(
            ['avatar' => 'filtered-https://example.test/avatar.png'],
            $row->getAvatarQueryParameters($filter)
        );
        $this->assertSame('https://example.test/avatar.png', $filter->avatarArgument);

        $this->assertSame(
            ['country' => 'filtered-gb'],
            $row->getCountryQueryParameters($filter)
        );
        $this->assertSame('gb', $filter->countryArgument);
    }
}
