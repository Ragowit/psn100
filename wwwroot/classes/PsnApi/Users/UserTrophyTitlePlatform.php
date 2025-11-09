<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

final class UserTrophyTitlePlatform
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }
}
