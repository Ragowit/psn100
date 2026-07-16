<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/PlatformSql.php';

final class PlatformSqlTest extends TestCase
{
    public function testBuildOrExpressionReturnsNullForUnknownPlatforms(): void
    {
        $this->assertNull(PlatformSql::buildOrExpression(['unknown']));
    }

    public function testBuildOrClausePrefixesAndForKnownPlatforms(): void
    {
        $clause = PlatformSql::buildOrClause(['ps4', 'psvr']);

        $this->assertStringStartsWith(' AND (', $clause);
        $this->assertStringContainsString("tt.platform LIKE '%PS4%'", $clause);
        $this->assertStringContainsString(PlatformSql::PSVR_TOKEN_MATCH, $clause);
        $this->assertStringNotContainsString('REGEXP_LIKE', $clause);
    }

    public function testPsvrPredicateDoesNotSubstringMatchPsvr2(): void
    {
        $psvr = PlatformSql::conditionFor('psvr');
        $this->assertNotNull($psvr);
        $this->assertStringContainsString("LIKE '%,PSVR,%'", $psvr);
        $this->assertStringNotContainsString('%PSVR%', $psvr);
    }
}
