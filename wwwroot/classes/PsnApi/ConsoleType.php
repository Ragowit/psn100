<?php

declare(strict_types=1);

namespace PsnApi;

final class ConsoleType
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
