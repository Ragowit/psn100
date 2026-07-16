<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterRuleConditionParser.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterRuleGroup.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterRulesCatalog.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterService.php';

final class PossibleCheaterServiceTest extends TestCase
{
    private PDO $database;
    private PossibleCheaterService $service;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->database->exec(
            <<<SQL
            CREATE TABLE player (
                account_id INTEGER PRIMARY KEY,
                online_id TEXT NOT NULL,
                status INTEGER NOT NULL DEFAULT 0
            );

            CREATE TABLE trophy_title (
                id INTEGER PRIMARY KEY,
                np_communication_id TEXT NOT NULL,
                name TEXT NOT NULL
            );

            CREATE TABLE trophy_earned (
                np_communication_id TEXT NOT NULL,
                group_id TEXT NOT NULL DEFAULT 'default',
                order_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                earned_date TEXT,
                progress INTEGER,
                earned INTEGER NOT NULL DEFAULT 1
            );
            SQL
        );

        $rulesCatalog = new PossibleCheaterRulesCatalog(
            $this->createGeneralRulesFile([
                [
                    'label' => 'Luftrausers',
                    'conditions' => [
                        "te.np_communication_id = 'NPWR05066_00' AND te.order_id = 2",
                    ],
                ],
                [
                    'label' => 'Planet Minigolf',
                    'conditions' => [
                        "te.np_communication_id = 'NPWR00550_00' AND te.order_id = 4 AND te.earned_date >= '2015-01-01'",
                    ],
                ],
            ]),
            $this->createSectionsFile([
                [
                    'title' => 'FUEL',
                    'query' => <<<'SQL'
                        SELECT
                            p.account_id,
                            p.online_id
                        FROM
                            trophy_earned fuel_start
                        JOIN trophy_earned fuel_end ON
                            fuel_end.account_id = fuel_start.account_id
                            AND fuel_end.np_communication_id = 'NPWR00481_00'
                            AND fuel_end.order_id = 34
                        JOIN player p ON
                            p.account_id = fuel_start.account_id
                            AND p.status != 1
                        WHERE
                            fuel_start.np_communication_id = 'NPWR00481_00'
                            AND fuel_start.order_id = 33
                            AND ABS(strftime('%s', fuel_end.earned_date) - strftime('%s', fuel_start.earned_date)) <= 60
                        ORDER BY
                            p.online_id
                    SQL,
                    'linkPattern' => '/game/4390-fuel/%s?sort=date',
                ],
            ])
        );

        $this->service = new PossibleCheaterService($this->database, $rulesCatalog);
    }

    public function testGeneralCheaterQueryRequiresEarnedTrophies(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterService.php');

        $this->assertStringContainsString('AND te.earned = 1', $source);
    }

    public function testCreateReportFindsMatchingGeneralCheaters(): void
    {
        $this->database->exec(
            <<<SQL
            INSERT INTO player (account_id, online_id, status) VALUES
                (1, 'CheaterOne', 0),
                (2, 'LegitPlayer', 0),
                (3, 'TaggedCheater', 1);

            INSERT INTO trophy_title (id, np_communication_id, name) VALUES
                (10, 'NPWR05066_00', 'Luftrausers'),
                (11, 'NPWR99999_00', 'Other Game');

            INSERT INTO trophy_earned (np_communication_id, group_id, order_id, account_id, earned_date) VALUES
                ('NPWR05066_00', 'default', 2, 1, '2020-01-01 00:00:00'),
                ('NPWR99999_00', 'default', 0, 1, '2019-01-01 00:00:00'),
                ('NPWR05066_00', 'default', 2, 3, '2020-01-01 00:00:00');
            SQL
        );

        $report = $this->service->createReport();
        $generalCheaters = $report->getGeneralCheaters();

        $this->assertCount(1, $generalCheaters);
        $this->assertSame('CheaterOne', $generalCheaters[0]->getPlayerName());
        $this->assertSame(1, $generalCheaters[0]->getAccountId());
    }

    public function testCreateReportAppliesEarnedDateConstraint(): void
    {
        $this->database->exec(
            <<<SQL
            INSERT INTO player (account_id, online_id, status) VALUES
                (4, 'TooEarly', 0),
                (5, 'JustRight', 0);

            INSERT INTO trophy_title (id, np_communication_id, name) VALUES
                (12, 'NPWR00550_00', 'Planet Minigolf');

            INSERT INTO trophy_earned (np_communication_id, group_id, order_id, account_id, earned_date) VALUES
                ('NPWR00550_00', 'default', 4, 4, '2014-12-31 23:59:59'),
                ('NPWR00550_00', 'default', 4, 5, '2015-01-01 00:00:00');
            SQL
        );

        $report = $this->service->createReport();
        $playerNames = array_map(
            static fn($entry) => $entry->getPlayerName(),
            $report->getGeneralCheaters()
        );

        $this->assertTrue(in_array('JustRight', $playerNames, true));
        $this->assertFalse(in_array('TooEarly', $playerNames, true));
    }

    public function testCreateReportBuildsSectionEntries(): void
    {
        $this->database->exec(
            <<<SQL
            INSERT INTO player (account_id, online_id, status) VALUES
                (6, 'FastFuel', 0);

            INSERT INTO trophy_earned (np_communication_id, group_id, order_id, account_id, earned_date) VALUES
                ('NPWR00481_00', 'default', 33, 6, '2020-01-01 00:00:00'),
                ('NPWR00481_00', 'default', 34, 6, '2020-01-01 00:00:30');
            SQL
        );

        $report = $this->service->createReport();
        $sections = $report->getSections();

        $this->assertCount(1, $sections);
        $this->assertSame('FUEL', $sections[0]->getTitle());
        $this->assertCount(1, $sections[0]->getEntries());
        $this->assertSame('FastFuel', $sections[0]->getEntries()[0]->getOnlineId());
        $this->assertSame('/game/4390-fuel/FastFuel?sort=date', $sections[0]->getEntries()[0]->getUrl());
    }

    /**
     * @param list<array<string, mixed>> $groups
     */
    private function createGeneralRulesFile(array $groups): string
    {
        $path = sys_get_temp_dir() . '/possible-cheater-general-' . uniqid('', true) . '.php';
        file_put_contents($path, '<?php return ' . var_export($groups, true) . ';');

        return $path;
    }

    /**
     * @param list<array<string, mixed>> $sections
     */
    private function createSectionsFile(array $sections): string
    {
        $path = sys_get_temp_dir() . '/possible-cheater-sections-' . uniqid('', true) . '.php';
        file_put_contents($path, '<?php return ' . var_export($sections, true) . ';');

        return $path;
    }
}
