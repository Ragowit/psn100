<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/GameTrophySort.php';
require_once __DIR__ . '/../wwwroot/classes/HttpMethod.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerAction.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerSortField.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/WorkerSortDirection.php';
require_once __DIR__ . '/../wwwroot/classes/TrophyRarityName.php';

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
}
