<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Platform.php';
require_once __DIR__ . '/../wwwroot/classes/PlatformSql.php';

final class PlatformSqlTest extends TestCase
{
    public function testBuildOrExpressionReturnsNullForUnknownPlatforms(): void
    {
        $this->assertSame(null, PlatformSql::buildOrExpression(['unknown']));
    }

    public function testBuildOrClausePrefixesAndForKnownPlatforms(): void
    {
        $clause = PlatformSql::buildOrClause(['ps4', 'psvr']);

        $this->assertTrue(str_starts_with($clause, ' AND ('));
        $this->assertStringContainsString("tt.platform LIKE '%PS4%'", $clause);
        $this->assertStringContainsString(PlatformSql::PSVR_TOKEN_MATCH, $clause);
        $this->assertFalse(str_contains($clause, 'REGEXP_LIKE'));
    }

    public function testPsvrPredicateDoesNotSubstringMatchPsvr2(): void
    {
        $psvr = PlatformSql::conditionFor(Platform::PsVr);
        $this->assertStringContainsString("LIKE '%,PSVR,%'", $psvr);
        $this->assertFalse(str_contains($psvr, "LIKE '%PSVR%'"));
    }
}
