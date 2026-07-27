<?php

declare(strict_types=1);

final readonly class PsnpPlusFixedGame
{
    /**
     * @var int[]
     */
    private array $trophyIds;

    /**
     * @param int[] $trophyIds
     */
    public function __construct(
        final private int $gameId,
        final private string $gameName,
        array $trophyIds,
    ) {
        $this->trophyIds = array_map(intval(...), $trophyIds);
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getGameName(): string
    {
        return $this->gameName;
    }

    /**
     * @return int[]
     */
    public function getTrophyIds(): array
    {
        return $this->trophyIds;
    }

    public function hasTrophies(): bool
    {
        return $this->trophyIds !== [];
    }

    public function getTrophyIdList(): string
    {
        return $this->formatList($this->trophyIds);
    }

    public function getTrophyIdQuery(): string
    {
        return $this->trophyIds
            |> (fn (array $ids): array => array_map(strval(...), $ids))
            |> (fn (array $ids): string => implode(',', $ids));
    }

    /**
     * @param int[] $values
     */
    private function formatList(array $values): string
    {
        return $values
            |> (fn (array $ids): array => array_map(strval(...), $ids))
            |> (fn (array $ids): string => implode(', ', $ids));
    }
}
