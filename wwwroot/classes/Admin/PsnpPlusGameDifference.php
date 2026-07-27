<?php

declare(strict_types=1);

final readonly class PsnpPlusGameDifference
{
    /**
     * @param int[] $unobtainableOrders
     * @param int[] $unobtainableTrophyIds
     * @param int[] $obtainableOrders
     * @param int[] $obtainableTrophyIds
     */
    public function __construct(
        final private int $gameId,
        final private string $gameName,
        final private string $npCommunicationId,
        final private int $psnprofilesId,
        /** @var int[] */
        final private array $unobtainableOrders,
        /** @var int[] */
        final private array $unobtainableTrophyIds,
        /** @var int[] */
        final private array $obtainableOrders,
        /** @var int[] */
        final private array $obtainableTrophyIds,
    ) {
        $this->unobtainableOrders = array_map(intval(...), $unobtainableOrders);
        $this->unobtainableTrophyIds = array_map(intval(...), $unobtainableTrophyIds);
        $this->obtainableOrders = array_map(intval(...), $obtainableOrders);
        $this->obtainableTrophyIds = array_map(intval(...), $obtainableTrophyIds);
    }

    public function getGameId(): int
    {
        return $this->gameId;
    }

    public function getGameName(): string
    {
        return $this->gameName;
    }

    public function getNpCommunicationId(): string
    {
        return $this->npCommunicationId;
    }

    public function getPsnprofilesId(): int
    {
        return $this->psnprofilesId;
    }

    /**
     * @return int[]
     */
    public function getUnobtainableOrders(): array
    {
        return $this->unobtainableOrders;
    }

    public function hasUnobtainable(): bool
    {
        return $this->unobtainableOrders !== [];
    }

    public function getUnobtainableOrderList(): string
    {
        return $this->formatList($this->unobtainableOrders);
    }

    /**
     * @return int[]
     */
    public function getUnobtainableTrophyIds(): array
    {
        return $this->unobtainableTrophyIds;
    }

    public function getUnobtainableTrophyIdQuery(): string
    {
        return $this->formatQuery($this->unobtainableTrophyIds);
    }

    /**
     * @return int[]
     */
    public function getObtainableOrders(): array
    {
        return $this->obtainableOrders;
    }

    public function hasObtainable(): bool
    {
        return $this->obtainableOrders !== [];
    }

    public function getObtainableOrderList(): string
    {
        return $this->formatList($this->obtainableOrders);
    }

    /**
     * @return int[]
     */
    public function getObtainableTrophyIds(): array
    {
        return $this->obtainableTrophyIds;
    }

    public function getObtainableTrophyIdQuery(): string
    {
        return $this->formatQuery($this->obtainableTrophyIds);
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

    /**
     * @param int[] $values
     */
    private function formatQuery(array $values): string
    {
        return $values
            |> (fn (array $ids): array => array_map(strval(...), $ids))
            |> (fn (array $ids): string => implode(',', $ids));
    }
}
