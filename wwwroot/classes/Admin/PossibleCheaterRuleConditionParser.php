<?php

declare(strict_types=1);

require_once __DIR__ . '/PossibleCheaterDateOperator.php';
require_once __DIR__ . '/PossibleCheaterRuleGroup.php';
require_once __DIR__ . '/PossibleCheaterRuleTuple.php';

final readonly class PossibleCheaterRuleConditionParser
{
    private const string CONDITION_PATTERN = '/^te\.np_communication_id = \'([^\']+)\' AND te\.order_id = (\d+)(?: AND te\.earned_date (>=|<=|<) \'([^\']+)\')?$/';

    #[\NoDiscard]
    public function parse(string $condition): PossibleCheaterRuleTuple
    {
        if (preg_match(self::CONDITION_PATTERN, $condition, $matches) !== 1) {
            throw new InvalidArgumentException('Unsupported possible cheater rule condition: ' . $condition);
        }

        return new PossibleCheaterRuleTuple(
            $matches[1],
            (int) $matches[2],
            PossibleCheaterDateOperator::tryFromMixed($matches[3] ?? null),
            isset($matches[4]) && $matches[4] !== '' ? $matches[4] : null
        );
    }

    /**
     * @param PossibleCheaterRuleGroup[] $groups
     */
    #[\NoDiscard]
    public function buildRuleDerivedTableSql(array $groups): string
    {
        $selects = [];

        foreach ($groups as $group) {
            foreach ($group->getRules() as $rule) {
                $selects[] = $this->parse($rule->getCondition())->toUnionSelect();
            }
        }

        if ($selects === []) {
            return 'SELECT NULL AS np_communication_id, NULL AS order_id, NULL AS date_operator, NULL AS date_value WHERE 0';
        }

        return implode(' UNION ALL ', $selects);
    }
}
