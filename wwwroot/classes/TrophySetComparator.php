<?php

declare(strict_types=1);

/**
 * Compares two trophy sets to decide whether automatic title merges are safe.
 */
final class TrophySetComparator
{
    /**
     * @param list<array{group_id:string, order_id:int, name:string, detail:string}> $leftTrophies
     * @param list<array{group_id:string, order_id:int, name:string, detail:string}> $rightTrophies
     * @return array{matches:bool, orderMatches:bool, nameMatches:bool}
     */
    public function compare(array $leftTrophies, array $rightTrophies): array
    {
        if (count($leftTrophies) !== count($rightTrophies)) {
            return ['matches' => false, 'orderMatches' => false, 'nameMatches' => false];
        }

        if ($leftTrophies === [] && $rightTrophies === []) {
            return ['matches' => true, 'orderMatches' => true, 'nameMatches' => true];
        }

        $leftCounts = $this->createNameDetailCounter($leftTrophies);
        $rightCounts = $this->createNameDetailCounter($rightTrophies);

        if ($leftCounts !== $rightCounts) {
            return ['matches' => false, 'orderMatches' => false, 'nameMatches' => false];
        }

        $orderMatches = $this->trophiesMatchByOrder($leftTrophies, $rightTrophies);
        $nameMatches = $this->trophiesMatchByName($leftTrophies, $rightTrophies);

        return ['matches' => true, 'orderMatches' => $orderMatches, 'nameMatches' => $nameMatches];
    }

    /**
     * @param array{matches:bool, orderMatches:bool, nameMatches:bool} $comparison
     */
    public function selectMergeMethod(array $comparison): ?string
    {
        if ($comparison['orderMatches']) {
            return 'order';
        }

        if ($comparison['nameMatches']) {
            return 'name';
        }

        return null;
    }

    /**
     * @param list<array{group_id:string, order_id:int, name:string, detail:string}> $left
     * @param list<array{group_id:string, order_id:int, name:string, detail:string}> $right
     */
    private function trophiesMatchByOrder(array $left, array $right): bool
    {
        $lookup = [];

        foreach ($left as $trophy) {
            $key = $this->createOrderKey($trophy['group_id'], $trophy['order_id']);
            $lookup[$key] = $this->createTrophyKey($trophy['name'], $trophy['detail']);
        }

        foreach ($right as $trophy) {
            $key = $this->createOrderKey($trophy['group_id'], $trophy['order_id']);
            $value = $this->createTrophyKey($trophy['name'], $trophy['detail']);

            if (!isset($lookup[$key]) || $lookup[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array{group_id:string, order_id:int, name:string, detail:string}> $trophies
     * @return array<string,int>
     */
    private function createNameDetailCounter(array $trophies): array
    {
        $counts = [];

        foreach ($trophies as $trophy) {
            $key = $this->createTrophyKey($trophy['name'], $trophy['detail']);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param list<array{group_id:string, order_id:int, name:string, detail:string}> $left
     * @param list<array{group_id:string, order_id:int, name:string, detail:string}> $right
     */
    private function trophiesMatchByName(array $left, array $right): bool
    {
        $leftNames = array_map(fn (array $trophy): string => $this->normalizeString($trophy['name']), $left);
        $rightNames = array_map(fn (array $trophy): string => $this->normalizeString($trophy['name']), $right);

        sort($leftNames);
        sort($rightNames);

        if ($leftNames !== $rightNames) {
            return false;
        }

        return count($leftNames) === count(array_unique($leftNames));
    }

    private function createTrophyKey(string $name, string $detail): string
    {
        return $this->normalizeString($name) . "\0" . $this->normalizeString($detail);
    }

    private function createOrderKey(string $groupId, int $orderId): string
    {
        return $groupId . '|' . $orderId;
    }

    private function normalizeString(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
