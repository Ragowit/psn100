<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Trophies;

final class TitleTrophyGroup
{
    private string $id;

    private string $name;

    private string $iconUrl;

    private string $detail;

    /** @var list<TitleTrophy> */
    private array $trophies;

    /**
     * @param list<TitleTrophy> $trophies
     */
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
     * @return list<TitleTrophy>
     */
    public function trophies(): array
    {
        return $this->trophies;
    }
}
