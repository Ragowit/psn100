<?php

declare(strict_types=1);

/**
 * Known PlayStation trophy group identifiers.
 *
 * Sony uses the literal "default" for a title's base trophy list; DLC groups use
 * other identifiers (typically numeric strings).
 */
enum TrophyGroupId: string
{
    case Default = 'default';

    /**
     * Quote the enum value for safe embedding in SQL string literals.
     */
    #[\NoDiscard]
    public function toSqlLiteral(): string
    {
        return "'" . $this->value . "'";
    }

    #[\NoDiscard]
    public function isDefault(): bool
    {
        return $this === self::Default;
    }
}
