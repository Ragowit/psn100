<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';

final class ReplacedTitlesIndexTest extends TestCase
{
    public function testQueryAndSchemaUseMultiValuedObsoleteTitleIndex(): void
    {
        $service = file_get_contents(__DIR__ . '/../wwwroot/classes/GameHeaderService.php');
        $schema = file_get_contents(__DIR__ . '/../database/psn100.sql');
        $migration = file_get_contents(__DIR__ . '/../database/mysql84_replaced_titles_index.sql');

        $this->assertStringContainsString('MEMBER OF (ttm.obsolete_ids_json)', $service);
        $this->assertStringContainsString('`obsolete_ids_json` json GENERATED ALWAYS AS', $schema);
        $this->assertStringContainsString('`idx_ttm_obsolete_ids` ((CAST(`obsolete_ids_json` AS UNSIGNED ARRAY)))', $schema);
        $this->assertStringContainsString('idx_ttm_obsolete_ids', $migration);
    }
}
