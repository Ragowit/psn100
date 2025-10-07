<?php

declare(strict_types=1);

final class PossibleCheaterRule
{
    public function __construct(private string $condition)
    {
    }

    public static function fromString(string $condition): self
    {
        return new self($condition);
    }

    public function getCondition(): string
    {
        return $this->condition;
    }
}

final class PossibleCheaterRuleGroup
{
    /**
     * @param PossibleCheaterRule[] $rules
     */
    public function __construct(private string $label, private array $rules)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $label = (string) ($data['label'] ?? '');
        $conditions = is_array($data['conditions'] ?? null) ? $data['conditions'] : [];

        $rules = [];
        foreach ($conditions as $condition) {
            $rules[] = PossibleCheaterRule::fromString((string) $condition);
        }

        return new self($label, $rules);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return PossibleCheaterRule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }
}
