<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameTrophySort.php';
require_once __DIR__ . '/../wwwroot/classes/HttpMethod.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerAction.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerSortField.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerSortDirection.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyRarityName.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerQueueMessagePartType.php';
require_once __DIR__ . '/../wwwroot/classes/JsonResponseStatus.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyType.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyMetaStatus.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineStatus.php';
require_once __DIR__ . '/../wwwroot/classes/HistoryIconType.php';
require_once __DIR__ . '/../wwwroot/classes/HistoryDiffTokenState.php';
require_once __DIR__ . '/../wwwroot/classes/LeaderboardView.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerRouteView.php';
require_once __DIR__ . '/../wwwroot/classes/RouteName.php';
require_once __DIR__ . '/../wwwroot/classes/AvatarSize.php';
require_once __DIR__ . '/../wwwroot/classes/GameRegion.php';
require_once __DIR__ . '/../wwwroot/classes/NpServiceName.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterDateOperator.php';
require_once __DIR__ . '/../wwwroot/classes/PlayerTimelineEntry.php';
require_once __DIR__ . '/../wwwroot/classes/Platform.php';

final class Php85IdiomEnumsTest extends TestCase
{
    public function testGameTrophySortFromMixedNormalizesValues(): void
    {
        $this->assertSame(GameTrophySort::Default, GameTrophySort::fromMixed(null));
        $this->assertSame(GameTrophySort::Default, GameTrophySort::fromMixed('unknown'));
        $this->assertSame(GameTrophySort::Date, GameTrophySort::fromMixed(' Date '));
        $this->assertSame(GameTrophySort::Rarity, GameTrophySort::fromMixed('RARITY'));
    }

    public function testHttpMethodFromServerDefaultsToGet(): void
    {
        $this->assertSame(HttpMethod::Get, HttpMethod::fromServer([]));
        $this->assertSame(HttpMethod::Post, HttpMethod::fromServer(['REQUEST_METHOD' => 'post']));
        $this->assertTrue(HttpMethod::fromMixed('POST')->isPost());
        $this->assertTrue(HttpMethod::fromMixed('GET')->isGet());
    }

    public function testWorkerActionTryFromMixedNormalizesValues(): void
    {
        $this->assertSame(WorkerAction::UpdateNpsso, WorkerAction::tryFromMixed(' UPDATE_NPSSO '));
        $this->assertSame(WorkerAction::RestartAllWorkers, WorkerAction::tryFromMixed('restart_all_workers'));
        $this->assertSame(null, WorkerAction::tryFromMixed('unknown'));
        $this->assertSame(null, WorkerAction::tryFromMixed(null));
    }

    public function testTrophyRarityNameFromMixedNormalizesValues(): void
    {
        $this->assertSame(TrophyRarityName::None, TrophyRarityName::fromMixed(null));
        $this->assertSame(TrophyRarityName::None, TrophyRarityName::fromMixed('unknown'));
        $this->assertSame(TrophyRarityName::Common, TrophyRarityName::fromMixed(' common '));
        $this->assertSame(TrophyRarityName::Legendary, TrophyRarityName::fromMixed('LEGENDARY'));
        $this->assertSame("'EPIC'", TrophyRarityName::Epic->toSqlLiteral());
    }

    public function testWorkerSortFieldAndDirectionFromMixed(): void
    {
        $this->assertSame(WorkerSortField::ScanStart, WorkerSortField::fromMixed(null));
        $this->assertSame(WorkerSortField::Id, WorkerSortField::fromMixed(' ID '));
        $this->assertSame(WorkerSortDirection::Asc, WorkerSortDirection::fromMixed(null));
        $this->assertSame(WorkerSortDirection::Desc, WorkerSortDirection::fromMixed(' DESC '));
        $this->assertSame(WorkerSortDirection::Asc, WorkerSortDirection::Desc->toggled());
    }

    public function testPlayerQueueMessagePartTypeTryFromMixed(): void
    {
        $this->assertSame(PlayerQueueMessagePartType::Text, PlayerQueueMessagePartType::tryFromMixed(' TEXT '));
        $this->assertSame(PlayerQueueMessagePartType::Progress, PlayerQueueMessagePartType::tryFromMixed('progress'));
        $this->assertSame(null, PlayerQueueMessagePartType::tryFromMixed('unknown'));
        $this->assertSame(null, PlayerQueueMessagePartType::tryFromMixed(null));
    }

    public function testJsonResponseStatusValues(): void
    {
        $this->assertSame('ok', JsonResponseStatus::Ok->value);
        $this->assertSame('error', JsonResponseStatus::Error->value);
    }

    public function testTrophyTypeFromMixedDefaultsToBronze(): void
    {
        $this->assertSame(TrophyType::Bronze, TrophyType::fromMixed(null));
        $this->assertSame(TrophyType::Bronze, TrophyType::fromMixed(''));
        $this->assertSame(TrophyType::Gold, TrophyType::fromMixed(' GOLD '));
        $this->assertSame(TrophyType::Platinum, TrophyType::fromMixed('platinum'));
    }

    public function testTrophyMetaStatusFromMixed(): void
    {
        $this->assertSame(TrophyMetaStatus::Unobtainable, TrophyMetaStatus::fromMixed('1'));
        $this->assertSame(TrophyMetaStatus::Obtainable, TrophyMetaStatus::fromMixed('0'));
        $this->assertSame(TrophyMetaStatus::Obtainable, TrophyMetaStatus::fromMixed('unknown'));
    }

    public function testPlayerTimelineStatusAndHistoryIconType(): void
    {
        $this->assertSame('completed', PlayerTimelineStatus::Completed->cssClass());
        $this->assertSame('stalled', PlayerTimelineStatus::Stalled->value);
        $this->assertSame('group', HistoryIconType::Group->value);
        $this->assertSame('title', HistoryIconType::Title->display()['directory']);

        $completed = PlayerTimelineEntry::fromRow([
            'game_id' => 1,
            'name' => 'Done',
            'progress' => 100,
            'first_trophy' => '2024-01-01',
            'last_trophy' => '2024-01-02',
        ]);
        $this->assertSame(PlayerTimelineStatus::Completed, $completed->getStatus(new DateTimeImmutable('2024-06-01')));

        $stalled = PlayerTimelineEntry::fromRow([
            'game_id' => 2,
            'name' => 'Idle',
            'progress' => 40,
            'first_trophy' => '2023-01-01',
            'last_trophy' => '2023-01-02',
        ]);
        $this->assertSame(PlayerTimelineStatus::Stalled, $stalled->getStatus(new DateTimeImmutable('2024-06-01')));

        $playing = PlayerTimelineEntry::fromRow([
            'game_id' => 3,
            'name' => 'Active',
            'progress' => 40,
            'first_trophy' => '2024-05-01',
            'last_trophy' => '2024-05-20',
        ]);
        $this->assertSame(PlayerTimelineStatus::Playing, $playing->getStatus(new DateTimeImmutable('2024-06-01')));
    }

    public function testPlatformUsesPlayStation5Assets(): void
    {
        $this->assertTrue(Platform::usesPlayStation5Assets('PS5'));
        $this->assertTrue(Platform::usesPlayStation5Assets('PS4, PSVR2'));
        $this->assertFalse(Platform::usesPlayStation5Assets('PS4, PS3'));
        $this->assertTrue(Platform::anyUsesPlayStation5Assets(['PS4', 'PS5']));
        $this->assertFalse(Platform::anyUsesPlayStation5Assets(['PS4', 'PC']));
        $this->assertSame(
            ['PS3', 'PS4', 'PSVR', 'PSVITA'],
            Platform::legacyTrophyServiceLabels()
        );
        $this->assertSame(
            [
                'pc' => 'PC',
                'ps3' => 'PS3',
                'ps4' => 'PS4',
                'ps5' => 'PS5',
                'psvita' => 'PSVITA',
                'psvr' => 'PSVR',
                'psvr2' => 'PSVR2',
            ],
            Platform::labelsByValue()
        );
    }

    public function testTrophyTypeSqlFieldOrderUsesPipeFriendlyCases(): void
    {
        $this->assertSame(
            "FIELD(t.type, 'bronze', 'silver', 'gold', 'platinum')",
            TrophyType::sqlFieldOrder('t.type')
        );
    }

    public function testHistoryDiffTokenStateHelpers(): void
    {
        $this->assertTrue(HistoryDiffTokenState::Equal->isEqual());
        $this->assertFalse(HistoryDiffTokenState::Removed->isEqual());
        $this->assertSame('added', HistoryDiffTokenState::Added->value);
    }

    public function testLeaderboardViewIncludeFiles(): void
    {
        $this->assertSame('leaderboard_main.php', LeaderboardView::Main->includeFile());
        $this->assertSame('leaderboard_main.php', LeaderboardView::Trophy->includeFile());
        $this->assertSame('leaderboard_rarity.php', LeaderboardView::Rarity->includeFile());
        $this->assertSame('leaderboard_in_game_rarity.php', LeaderboardView::InGameRarity->includeFile());
        $this->assertSame(null, LeaderboardView::tryFrom('unknown'));
    }

    public function testPlayerRouteViewIncludeFiles(): void
    {
        $this->assertSame('player_advisor.php', PlayerRouteView::Advisor->includeFile());
        $this->assertSame('player_log.php', PlayerRouteView::Log->includeFile());
        $this->assertSame('player_random.php', PlayerRouteView::Random->includeFile());
        $this->assertSame('player_report.php', PlayerRouteView::Report->includeFile());
        $this->assertSame('player_timeline.php', PlayerRouteView::Timeline->includeFile());
        $this->assertSame(null, PlayerRouteView::tryFrom('unknown'));
    }

    public function testRouteNameValues(): void
    {
        $this->assertSame('game-history', RouteName::GameHistory->value);
        $this->assertSame('player', RouteName::Player->value);
        $this->assertSame(RouteName::Trophy, RouteName::tryFrom('trophy'));
        $this->assertSame(null, RouteName::tryFrom('unknown'));
    }

    public function testAvatarSizePreferenceOrder(): void
    {
        $this->assertSame(
            [AvatarSize::Xl, AvatarSize::L, AvatarSize::M, AvatarSize::S],
            AvatarSize::preferenceOrder()
        );
        $this->assertSame('xl', AvatarSize::Xl->value);
    }

    public function testGameRegionSqlSortCaseExpression(): void
    {
        $this->assertSame(0, GameRegion::Na->sortRank());
        $this->assertSame(3, GameRegion::Hk->sortRank());

        $expression = GameRegion::sqlSortCaseExpression('region');
        $this->assertStringContainsString("WHEN region = 'NA' THEN 0", $expression);
        $this->assertStringContainsString('WHEN region IS NULL THEN 2', $expression);
        $this->assertStringContainsString("WHEN region = 'AS' THEN 5", $expression);
        $this->assertStringContainsString('ELSE 6', $expression);
    }

    public function testNpServiceNamePreferForPlatformLabels(): void
    {
        $this->assertSame(NpServiceName::Trophy, NpServiceName::preferForPlatformLabels(['PS4']));
        $this->assertSame(NpServiceName::Trophy2, NpServiceName::preferForPlatformLabels(['PS5']));
        $this->assertSame(NpServiceName::Trophy2, NpServiceName::tryFromMixed(' TROPHY2 '));
        $this->assertSame(null, NpServiceName::tryFromMixed('unknown'));
    }

    public function testPossibleCheaterDateOperatorTryFromMixed(): void
    {
        $this->assertSame(PossibleCheaterDateOperator::GreaterThanOrEqual, PossibleCheaterDateOperator::tryFromMixed('>='));
        $this->assertSame(PossibleCheaterDateOperator::LessThanOrEqual, PossibleCheaterDateOperator::tryFromMixed('<='));
        $this->assertSame(PossibleCheaterDateOperator::LessThan, PossibleCheaterDateOperator::tryFromMixed('<'));
        $this->assertSame(null, PossibleCheaterDateOperator::tryFromMixed(''));
        $this->assertSame(null, PossibleCheaterDateOperator::tryFromMixed(null));
    }
}
