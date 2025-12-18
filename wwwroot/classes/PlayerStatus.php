<?php

declare(strict_types=1);

enum PlayerStatus: int
{
    case NORMAL = 0;
    case FLAGGED = 1;
    case PRIVATE_PROFILE = 3;
    case INACTIVE = 4;
    case UNAVAILABLE = 5;
    case NEW_PLAYER = 99;

    public static function fromValue(int $status): self
    {
        return self::tryFrom($status) ?? self::NORMAL;
    }

    public function isFlagged(): bool
    {
        return $this === self::FLAGGED;
    }

    public function isPrivateProfile(): bool
    {
        return $this === self::PRIVATE_PROFILE;
    }

    public function isRestricted(): bool
    {
        return $this->isFlagged() || $this->isPrivateProfile();
    }
}
