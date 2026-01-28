<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyMergeService.php';

final class AutomaticTrophyTitleMergeService
{
    private PDO $database;

    private TrophyMergeService $trophyMergeService;

    /** @var array<string, list<array{group_id:string, order_id:int, name:string, detail:string}>> */
    private array $trophyCache = [];

    public function __construct(PDO $database, TrophyMergeService $trophyMergeService)
    {
        $this->database = $database;
        $this->trophyMergeService = $trophyMergeService;
    }

    /**
     * @return list<string> Merge parent NP communication IDs to recompute.
     */
    public function handleNewTitle(string $npCommunicationId): array
    {
        $newTitle = $this->getTitleByNpCommunicationId($npCommunicationId);

        if ($newTitle === null) {
            return [];
        }

        $matchingTitles = $this->findMatchingTitles($newTitle);

        if ($matchingTitles === []) {
            return [];
        }

        $cloneCandidate = $this->selectCloneCandidate($matchingTitles);

        if ($cloneCandidate !== null) {
            $comparison = $this->compareTrophies(
                $cloneCandidate['np_communication_id'],
                $newTitle['np_communication_id']
            );

            if (!$comparison['matches']) {
                return [];
            }

            $mergeMethod = $this->selectMergeMethod($comparison);

            if ($mergeMethod === null) {
                $this->logAmbiguousNameMerge(
                    $newTitle['np_communication_id'],
                    $cloneCandidate['np_communication_id']
                );

                return [];
            }

            if ($this->shouldCopyPlatforms($cloneCandidate['platforms'], $newTitle['platforms'])) {
                $this->trophyMergeService->copyGameData(
                    $newTitle['np_communication_id'],
                    $cloneCandidate['np_communication_id']
                );

                $this->clearTrophyCache(
                    $newTitle['np_communication_id'],
                    $cloneCandidate['np_communication_id']
                );
            }

            $this->mergeAndReportWarnings(
                $newTitle['id'],
                $cloneCandidate['id'],
                $mergeMethod,
                $newTitle['np_communication_id'],
                $cloneCandidate['np_communication_id']
            );

            return [$cloneCandidate['np_communication_id']];
        }

        $gamesToMerge = $this->createUniqueGameList($newTitle, $matchingTitles);
        $gameToClone = $this->selectGameToClone($gamesToMerge);

        if ($gameToClone === null) {
            return [];
        }

        $mergeMethods = [];
        foreach ($gamesToMerge as $game) {
            $comparison = $this->compareTrophies(
                $gameToClone['np_communication_id'],
                $game['np_communication_id']
            );

            if (!$comparison['matches']) {
                continue;
            }

            $mergeMethod = $this->selectMergeMethod($comparison);

            if ($mergeMethod === null) {
                $this->logAmbiguousNameMerge(
                    $game['np_communication_id'],
                    $gameToClone['np_communication_id']
                );

                continue;
            }

            $mergeMethods[$game['id']] = $mergeMethod;
        }

        if ($mergeMethods === []) {
            return [];
        }

        $cloneInfo = $this->trophyMergeService->cloneGameWithInfo($gameToClone['id']);
        $parentGameId = (int) $cloneInfo['clone_game_id'];

        foreach ($gamesToMerge as $game) {
            if (!isset($mergeMethods[$game['id']])) {
                continue;
            }

            $this->mergeAndReportWarnings(
                $game['id'],
                $parentGameId,
                $mergeMethods[$game['id']],
                $game['np_communication_id'],
                $gameToClone['np_communication_id']
            );
        }

        return [$cloneInfo['clone_np_communication_id']];
    }

    public function recomputeMergeProgressByParent(string $parentNpCommunicationId): void
    {
        $this->trophyMergeService->recomputeMergeProgressByParent($parentNpCommunicationId);
    }

    /**
     * @return array{id:int, np_communication_id:string, name:string, platform:?string, platforms:string[], status:int}|null
     */
    private function getTitleByNpCommunicationId(string $npCommunicationId): ?array
    {
        $query = $this->database->prepare(
            'SELECT tt.id, tt.np_communication_id, tt.name, tt.platform, COALESCE(ttm.status, 0) AS status
            FROM trophy_title tt
            LEFT JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE tt.np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $platform = $row['platform'] ?? null;

        return [
            'id' => (int) $row['id'],
            'np_communication_id' => (string) $row['np_communication_id'],
            'name' => (string) $row['name'],
            'platform' => $platform,
            'platforms' => $this->parsePlatforms($platform),
            'status' => (int) ($row['status'] ?? 0),
        ];
    }

    /**
     * @param array{id:int, np_communication_id:string, name:string, platform:?string, platforms:string[], status:int} $newTitle
     * @return list<array{id:int, np_communication_id:string, platform:?string, platforms:string[], is_clone:bool, matches_by_order:bool, status:int}>
     */
    private function findMatchingTitles(array $newTitle): array
    {
        $query = $this->database->prepare(
            'SELECT tt.id, tt.np_communication_id, tt.platform, COALESCE(ttm.status, 0) AS status
            FROM trophy_title tt
            LEFT JOIN trophy_title_meta ttm ON ttm.np_communication_id = tt.np_communication_id
            WHERE tt.name = :name AND tt.np_communication_id != :np_communication_id AND COALESCE(ttm.status, 0) != 2'
        );
        $query->bindValue(':name', $newTitle['name'], PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $newTitle['np_communication_id'], PDO::PARAM_STR);
        $query->execute();

        $matches = [];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $comparison = $this->compareTrophies(
                $newTitle['np_communication_id'],
                (string) $row['np_communication_id']
            );

            if (!$comparison['matches']) {
                continue;
            }

            $npCommunicationId = (string) $row['np_communication_id'];
            $platform = $row['platform'] ?? null;

            $matches[] = [
                'id' => (int) $row['id'],
                'np_communication_id' => $npCommunicationId,
                'platform' => $platform,
                'platforms' => $this->parsePlatforms($platform),
                'is_clone' => str_starts_with($npCommunicationId, 'MERGE'),
                'matches_by_order' => $comparison['orderMatches'],
                'status' => (int) ($row['status'] ?? 0),
            ];
        }

        return $matches;
    }

    /**
     * @param list<array{id:int, np_communication_id:string, platform:?string, platforms:string[], is_clone:bool, matches_by_order:bool, status:int}> $matches
     * @return array{id:int, np_communication_id:string, platform:?string, platforms:string[], is_clone:bool, matches_by_order:bool, status:int}|null
     */
    private function selectCloneCandidate(array $matches): ?array
    {
        foreach ($matches as $candidate) {
            if ($candidate['is_clone'] && $candidate['matches_by_order']) {
                return $candidate;
            }
        }

        foreach ($matches as $candidate) {
            if ($candidate['is_clone']) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array{id:int, np_communication_id:string, name:string, platform:?string, platforms:string[], status:int} $newTitle
     * @param list<array{id:int, np_communication_id:string, platform:?string, platforms:string[], is_clone:bool, matches_by_order:bool, status:int}> $matches
     * @return list<array{id:int, np_communication_id:string, name:string, platform:?string, platforms:string[], status:int}>
     */
    private function createUniqueGameList(array $newTitle, array $matches): array
    {
        $games = [
            $newTitle['id'] => $newTitle,
        ];

        foreach ($matches as $match) {
            if (str_starts_with($match['np_communication_id'], 'MERGE')) {
                continue;
            }

            if ($match['status'] === 2) {
                continue;
            }

            $games[$match['id']] = [
                'id' => $match['id'],
                'np_communication_id' => $match['np_communication_id'],
                'name' => $newTitle['name'],
                'platform' => $match['platform'],
                'platforms' => $match['platforms'],
                'status' => $match['status'],
            ];
        }

        return array_values($games);
    }

    /**
     * @param list<array{id:int, np_communication_id:string, name:string, platform:?string, platforms:string[], status:int}> $games
     * @return array{id:int, np_communication_id:string, name:string, platform:?string, platforms:string[], status:int}|null
     */
    private function selectGameToClone(array $games): ?array
    {
        $eligibleGames = array_values(array_filter(
            $games,
            static fn (array $game): bool => !str_starts_with($game['np_communication_id'], 'MERGE') && $game['status'] !== 2
        ));

        foreach ($eligibleGames as $game) {
            if ($this->hasPs5OrPsvr2($game['platforms'])) {
                return $game;
            }
        }

        return $eligibleGames[0] ?? null;
    }

    /**
     * @param string $leftNpCommunicationId
     * @param string $rightNpCommunicationId
     * @return array{matches:bool, orderMatches:bool, nameMatches:bool}
     */
    private function compareTrophies(string $leftNpCommunicationId, string $rightNpCommunicationId): array
    {
        $leftTrophies = $this->getTrophiesByNpCommunicationId($leftNpCommunicationId);
        $rightTrophies = $this->getTrophiesByNpCommunicationId($rightNpCommunicationId);

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
        $leftNames = array_map(fn(array $trophy): string => $this->normalizeString($trophy['name']), $left);
        $rightNames = array_map(fn(array $trophy): string => $this->normalizeString($trophy['name']), $right);

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

    /**
     * @param array{matches:bool, orderMatches:bool, nameMatches:bool} $comparison
     */
    private function selectMergeMethod(array $comparison): ?string
    {
        if ($comparison['orderMatches']) {
            return 'order';
        }

        if ($comparison['nameMatches']) {
            return 'name';
        }

        return null;
    }

    private function logAmbiguousNameMerge(string $childNpCommunicationId, string $parentNpCommunicationId): void
    {
        error_log(
            sprintf(
                'Automatic trophy merge skipped due to ambiguous name mapping between %s and %s.',
                $childNpCommunicationId,
                $parentNpCommunicationId
            )
        );
    }

    private function mergeAndReportWarnings(
        int $childGameId,
        int $parentGameId,
        string $mergeMethod,
        string $childNpCommunicationId,
        string $parentNpCommunicationId
    ): void {
        $message = $this->trophyMergeService->mergeGames($childGameId, $parentGameId, $mergeMethod);

        if ($message !== 'The games have been merged.') {
            error_log(
                sprintf(
                    'Automatic trophy merge between %s and %s returned warnings: %s',
                    $childNpCommunicationId,
                    $parentNpCommunicationId,
                    $message
                )
            );
        }
    }

    /**
     * @return list<array{group_id:string, order_id:int, name:string, detail:string}>
     */
    private function getTrophiesByNpCommunicationId(string $npCommunicationId): array
    {
        if (isset($this->trophyCache[$npCommunicationId])) {
            return $this->trophyCache[$npCommunicationId];
        }

        $query = $this->database->prepare(
            'SELECT group_id, order_id, name, detail
            FROM trophy
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $trophies = [];

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $trophies[] = [
                'group_id' => (string) $row['group_id'],
                'order_id' => (int) $row['order_id'],
                'name' => trim((string) $row['name']),
                'detail' => trim((string) ($row['detail'] ?? '')),
            ];
        }

        $this->trophyCache[$npCommunicationId] = $trophies;

        return $trophies;
    }

    private function clearTrophyCache(string ...$npCommunicationIds): void
    {
        foreach ($npCommunicationIds as $npCommunicationId) {
            unset($this->trophyCache[$npCommunicationId]);
        }
    }

    /**
     * @param string[] $clonePlatforms
     * @param string[] $newPlatforms
     */
    private function shouldCopyPlatforms(array $clonePlatforms, array $newPlatforms): bool
    {
        if (!$this->hasPs5OrPsvr2($newPlatforms)) {
            return false;
        }

        return !$this->hasPs5OrPsvr2($clonePlatforms);
    }

    /**
     * @param string[] $platforms
     */
    private function hasPs5OrPsvr2(array $platforms): bool
    {
        foreach ($platforms as $platform) {
            if ($platform === 'PS5' || $platform === 'PSVR2') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function parsePlatforms(?string $platforms): array
    {
        if ($platforms === null || trim($platforms) === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $platforms));
        $parts = array_filter($parts, static fn(string $platform): bool => $platform !== '');

        return array_values(array_map('strtoupper', $parts));
    }
}
