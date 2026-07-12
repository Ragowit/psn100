<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterRuleConditionParser.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterRulesCatalog.php';

final class PossibleCheaterRuleConditionParserTest extends TestCase
{
    private PossibleCheaterRuleConditionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PossibleCheaterRuleConditionParser();
    }

    public function testParsesSimpleCondition(): void
    {
        $tuple = $this->parser->parse("te.np_communication_id = 'NPWR05066_00' AND te.order_id = 2");

        $this->assertSame('NPWR05066_00', $tuple->getNpCommunicationId());
        $this->assertSame(2, $tuple->getOrderId());
        $this->assertSame(null, $tuple->getDateOperator());
        $this->assertSame(null, $tuple->getDateValue());
    }

    public function testParsesConditionWithMinimumEarnedDate(): void
    {
        $tuple = $this->parser->parse(
            "te.np_communication_id = 'NPWR00550_00' AND te.order_id = 4 AND te.earned_date >= '2015-01-01'"
        );

        $this->assertSame('>=', $tuple->getDateOperator());
        $this->assertSame('2015-01-01', $tuple->getDateValue());
    }

    public function testParsesConditionWithMaximumEarnedDate(): void
    {
        $tuple = $this->parser->parse(
            "te.np_communication_id = 'NPWR14225_00' AND te.order_id = 6 AND te.earned_date <= '2020-04-12'"
        );

        $this->assertSame('<=', $tuple->getDateOperator());
        $this->assertSame('2020-04-12', $tuple->getDateValue());
    }

    public function testParsesConditionWithExclusiveMaximumEarnedDate(): void
    {
        $tuple = $this->parser->parse(
            "te.np_communication_id = 'NPWR18341_00' AND te.order_id = 0 AND te.earned_date < '2020-03-01'"
        );

        $this->assertSame('<', $tuple->getDateOperator());
        $this->assertSame('2020-03-01', $tuple->getDateValue());
    }

    public function testParsesMergedGameCondition(): void
    {
        $tuple = $this->parser->parse("te.np_communication_id = 'MERGE_011562' AND te.order_id = 0");

        $this->assertSame('MERGE_011562', $tuple->getNpCommunicationId());
        $this->assertSame(0, $tuple->getOrderId());
    }

    public function testParsesAllDefaultGeneralRuleConditions(): void
    {
        $catalog = new PossibleCheaterRulesCatalog();

        foreach ($catalog->getGeneralRuleGroups() as $group) {
            foreach ($group->getRules() as $rule) {
                $tuple = $this->parser->parse($rule->getCondition());
                $this->assertTrue($tuple->getNpCommunicationId() !== '');
            }
        }
    }

    public function testBuildRuleDerivedTableSqlIncludesDateConstraints(): void
    {
        $catalog = new PossibleCheaterRulesCatalog();
        $sql = $this->parser->buildRuleDerivedTableSql($catalog->getGeneralRuleGroups());

        $this->assertStringContainsString("SELECT 'NPWR05066_00' AS np_communication_id, 2 AS order_id", $sql);
        $this->assertStringContainsString("SELECT 'NPWR00550_00' AS np_communication_id, 4 AS order_id, '>=' AS date_operator, '2015-01-01' AS date_value", $sql);
        $this->assertStringContainsString("UNION ALL", $sql);
    }

    public function testRejectsUnsupportedCondition(): void
    {
        try {
            $this->parser->parse('te.progress > 0');
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unsupported possible cheater rule condition', $exception->getMessage());
        }
    }
}
