<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

final class UserTrophyGroup
{
    private string $id;

    private string $name;

    private string $iconUrl;

    private string $detail;

    /** @var list<UserTrophy> */
    private array $trophies;

    public function __construct(string $id, string $name, string $iconUrl, ?string $detail, array $trophies)
    {
        $this->id = $id;
        $this->name = $name;
        $this->iconUrl = $iconUrl;
        $this->detail = $detail ?? '';
        $this->trophies = $trophies;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function iconUrl(): string
    {
        return $this->iconUrl;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    /**
     * @return list<UserTrophy>
     */
    public function trophies(): array
    {
        return $this->trophies;
    }
}
