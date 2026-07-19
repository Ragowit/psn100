<?php

declare(strict_types=1);

require_once __DIR__ . '/PossibleCheaterRuleGroup.php';
require_once __DIR__ . '/PossibleCheaterSectionDefinition.php';

final class PossibleCheaterRulesCatalog
{
    private const string DEFAULT_GENERAL_RULES_FILE = __DIR__ . '/../../config/possible-cheater-general-rules.php';
    private const string DEFAULT_SECTIONS_FILE = __DIR__ . '/../../config/possible-cheater-sections.php';

    /**
     * @var PossibleCheaterRuleGroup[]|null
     */
    private ?array $generalRuleGroups = null;

    /**
     * @var PossibleCheaterSectionDefinition[]|null
     */
    private ?array $sectionDefinitions = null;

    public function __construct(
        private readonly string $generalRulesFile = self::DEFAULT_GENERAL_RULES_FILE,
        private readonly string $sectionsFile = self::DEFAULT_SECTIONS_FILE,
    ) {
    }

    /**
     * @return PossibleCheaterRuleGroup[]
     */
    public function getGeneralRuleGroups(): array
    {
        if ($this->generalRuleGroups === null) {
            $this->generalRuleGroups = array_map(
                PossibleCheaterRuleGroup::fromArray(...),
                $this->loadArrayFile($this->generalRulesFile)
            );
        }

        return $this->generalRuleGroups;
    }

    /**
     * @return PossibleCheaterSectionDefinition[]
     */
    public function getSectionDefinitions(): array
    {
        if ($this->sectionDefinitions === null) {
            $this->sectionDefinitions = array_map(
                PossibleCheaterSectionDefinition::fromArray(...),
                $this->loadArrayFile($this->sectionsFile)
            );
        }

        return $this->sectionDefinitions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadArrayFile(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException('Possible cheater rules file not found: ' . $path);
        }

        $data = require $path;
        if (!is_array($data)) {
            throw new RuntimeException('Possible cheater rules file must return an array: ' . $path);
        }

        return $data;
    }
}
