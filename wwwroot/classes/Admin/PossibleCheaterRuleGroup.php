<?php

declare(strict_types=1);

final readonly class PossibleCheaterRule
{
    public function __construct(private string $condition)
    {
    }

    #[\NoDiscard]
    public static function fromString(string $condition): self
    {
        return new self($condition);
    }

    public function getCondition(): string
    {
        return $this->condition;
    }
}

final readonly class PossibleCheaterRuleGroup
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
    #[\NoDiscard]
    public static function fromArray(array $data): self
    {
        $label = (string) ($data['label'] ?? '');
        $conditions = is_array($data['conditions'] ?? null) ? $data['conditions'] : [];

        $rules = array_map(
            static fn (mixed $condition): PossibleCheaterRule => PossibleCheaterRule::fromString((string) $condition),
            $conditions,
        );

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
