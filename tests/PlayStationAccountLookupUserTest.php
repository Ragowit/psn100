<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlayStation/Http/PlayStationAccountLookupUser.php';

final class PlayStationAccountLookupUserTest extends TestCase
{
    public function testFromPayloadBuildsMethodBackedUserContract(): void
    {
        $user = PlayStationAccountLookupUser::fromPayload([
            'profile' => [
                'accountId' => '12345',
                'onlineId' => 'PlayerOne',
                'aboutMe' => 'Hello world',
                'country' => 'us',
                'trophySummary' => [
                    'level' => 300,
                    'progress' => 12,
                    'earnedTrophies' => [
                        'platinum' => 1,
                        'gold' => 2,
                        'silver' => 3,
                        'bronze' => 4,
                    ],
                ],
            ],
        ]);

        $this->assertSame('12345', $user->accountId());
        $this->assertSame('PlayerOne', $user->onlineId());
        $this->assertSame('Hello world', $user->aboutMe());
        $this->assertSame('us', $user->country());
        $this->assertSame(300, $user->trophySummary()->level());
        $this->assertSame(1, $user->trophySummary()->platinum());
        $this->assertSame(2, $user->trophySummary()->gold());
        $this->assertSame(3, $user->trophySummary()->silver());
        $this->assertSame(4, $user->trophySummary()->bronze());
        $this->assertFalse($user->hasPlus());
        $this->assertSame([], $user->avatarUrls());
    }

    public function testFromPayloadDefaultsMissingTrophySummaryValuesToZero(): void
    {
        $user = PlayStationAccountLookupUser::fromPayload([
            'profile' => [
                'accountId' => '999',
                'onlineId' => 'NoTrophies',
            ],
        ]);

        $this->assertSame(0, $user->trophySummary()->level());
        $this->assertSame(0, $user->trophySummary()->progress());
        $this->assertSame(0, $user->trophySummary()->platinum());
        $this->assertSame(0, $user->trophySummary()->gold());
        $this->assertSame(0, $user->trophySummary()->silver());
        $this->assertSame(0, $user->trophySummary()->bronze());
    }

    public function testFromPayloadUsesFallbackAccountIdAndMapsAvatarUrls(): void
    {
        $user = PlayStationAccountLookupUser::fromPayload(
            [
                'onlineId' => 'Ragowit',
                'isPlus' => true,
                'avatars' => [
                    ['size' => 's', 'url' => 'https://example.com/s.png'],
                    ['size' => 'xl', 'url' => 'https://example.com/xl.png'],
                ],
            ],
            '1882371903386905898'
        );

        $this->assertSame('1882371903386905898', $user->accountId());
        $this->assertSame('Ragowit', $user->onlineId());
        $this->assertTrue($user->hasPlus());
        $this->assertSame(
            [
                's' => 'https://example.com/s.png',
                'xl' => 'https://example.com/xl.png',
            ],
            $user->avatarUrls()
        );
    }
}
