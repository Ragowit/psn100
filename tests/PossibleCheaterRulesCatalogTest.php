<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/PossibleCheaterRulesCatalog.php';

final class PossibleCheaterRulesCatalogTest extends TestCase
{
    public function testLoadsDefaultGeneralRuleGroups(): void
    {
        $catalog = new PossibleCheaterRulesCatalog();

        $groups = $catalog->getGeneralRuleGroups();

        $this->assertCount(73, $groups);
        $this->assertSame('Luftrausers', $groups[0]->getLabel());
        $this->assertCount(2, $groups[0]->getRules());
        $this->assertSame(
            "te.np_communication_id = 'NPWR05066_00' AND te.order_id = 2",
            $groups[0]->getRules()[0]->getCondition()
        );
    }

    public function testLoadsDefaultSectionDefinitions(): void
    {
        $catalog = new PossibleCheaterRulesCatalog();

        $sections = $catalog->getSectionDefinitions();

        $this->assertCount(28, $sections);
        $this->assertSame('FUEL', $sections[0]->getTitle());
        $this->assertStringContainsString('NPWR00481_00', $sections[0]->getQuery());
        $this->assertSame('/game/4390-fuel/test-player?sort=date', $sections[0]->buildLink('test-player'));
    }

    public function testLoadsCustomRuleFiles(): void
    {
        $temporaryDirectory = sys_get_temp_dir() . '/possible-cheater-rules-' . uniqid('', true);
        mkdir($temporaryDirectory, 0777, true);

        $generalRulesFile = $temporaryDirectory . '/general.php';
        $sectionsFile = $temporaryDirectory . '/sections.php';

        file_put_contents($generalRulesFile, <<<'PHP'
<?php

return [
    [
        'label' => 'Custom Game',
        'conditions' => [
            "te.np_communication_id = 'NPWR00000_00' AND te.order_id = 1",
        ],
    ],
];
PHP);
        file_put_contents($sectionsFile, <<<'PHP'
<?php

return [
    [
        'title' => 'Custom Section',
        'query' => 'SELECT 1',
        'linkPattern' => '/custom/%s',
    ],
];
PHP);

        try {
            $catalog = new PossibleCheaterRulesCatalog($generalRulesFile, $sectionsFile);

            $groups = $catalog->getGeneralRuleGroups();
            $sections = $catalog->getSectionDefinitions();

            $this->assertCount(1, $groups);
            $this->assertSame('Custom Game', $groups[0]->getLabel());
            $this->assertCount(1, $sections);
            $this->assertSame('Custom Section', $sections[0]->getTitle());
            $this->assertSame('/custom/example', $sections[0]->buildLink('example'));
        } finally {
            unlink($generalRulesFile);
            unlink($sectionsFile);
            rmdir($temporaryDirectory);
        }
    }
}
