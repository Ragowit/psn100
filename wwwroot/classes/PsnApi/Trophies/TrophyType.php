<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Trophies;

final class TrophyType
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = strtolower($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __get(string $name): string
    {
        if ($name === 'value') {
            return $this->value;
        }

        throw new \OutOfBoundsException(sprintf('Undefined property "%s" on %s.', $name, self::class));
    }
}
