<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnpPlusReport.php';
require_once __DIR__ . '/PsnpPlusGameDifference.php';
require_once __DIR__ . '/PsnpPlusFixedGame.php';
require_once __DIR__ . '/PsnpPlusMissingGame.php';
require_once __DIR__ . '/PsnpPlusGame.php';

class PsnpPlusService
{
    private const DATA_URL = 'https://psnp-plus.netlify.app/list.min.json';

    /**
     * @var int[]
     */
    private const UNRELEASED_GAMES = [
        2409, 2410, 2414, 2552, 4234, 4236, 4237, 4240, 4241, 5012, 5318, 5925, 6420, 7082, 7272, 8352, 8644, 8672,
        10225, 10314, 10519, 10601, 10834, 11153, 13787, 13788, 14299, 16636, 16827, 16907, 16935, 17071, 17166, 17244,
        17359, 17420, 17575, 17745, 18171, 18552, 18895,
        19215, 19384, 19652, 20031, 21204, 21794, 25746, 25749, 26964, 27034, 27039, 27040, 27294, 27804, 27979, 28410,
        29376, 29729, 29730, 30829, 32645, 33091, 33992,
        35758,
    ];

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    public function buildReport(): PsnpPlusReport
    {
        $psnpPlusList = $this->fetchPsnpPlusList();
        $foundNpCommunicationIds = [];
        $differences = [];
        $missingGames = [];

        foreach ($psnpPlusList as $psnprofilesId => $trophies) {
            if ($this->isUnreleasedGame($psnprofilesId)) {
                continue;
            }

            $game = $this->findGameByPsnprofilesId($psnprofilesId);

            if ($game === null) {
                $missingGames[] = new PsnpPlusMissingGame($psnprofilesId);
                continue;
            }

            $foundNpCommunicationIds[] = $game->getNpCommunicationId();

            $psnpTrophies = $trophies;
            if ($psnpTrophies !== [] && $psnpTrophies[0] === 0) {
                $psnpTrophies = $this->findTrophyOrderNumbers($game->getNpCommunicationId(), false);
            }

            $obtainableTrophies = $this->findTrophyOrderNumbers($game->getNpCommunicationId(), true);

            $unobtainable = $this->calculateDifference($psnpTrophies, $obtainableTrophies);
            $obtainable = $this->calculateDifference($obtainableTrophies, $psnpTrophies);

            if ($unobtainable !== [] || $obtainable !== []) {
                $differences[] = new PsnpPlusGameDifference(
                    $game->getId(),
                    $game->getName(),
                    $game->getNpCommunicationId(),
                    $psnprofilesId,
                    $unobtainable,
                    $this->findTrophyIdsForOrders($game->getNpCommunicationId(), $unobtainable),
                    $obtainable,
                    $this->findTrophyIdsForOrders($game->getNpCommunicationId(), $obtainable)
                );
            }
        }

        $fixedGames = $this->buildFixedGames($foundNpCommunicationIds);

        return new PsnpPlusReport($missingGames, $differences, $fixedGames);
    }

    /**
     * @return array<int, array<int>>
     */
    private function fetchPsnpPlusList(): array
    {
        $json = @file_get_contents(self::DATA_URL);

        if ($json === false) {
            throw new RuntimeException('Unable to download PSNP+ data.');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['list']) || !is_array($decoded['list'])) {
            throw new RuntimeException('Invalid PSNP+ data received.');
        }

        $result = [];
        foreach ($decoded['list'] as $psnprofilesId => $trophies) {
            if (!is_array($trophies)) {
                $trophies = [];
            }

            $result[(int) $psnprofilesId] = array_map('intval', $trophies);
        }

        return $result;
    }

    private function isUnreleasedGame(int $psnprofilesId): bool
    {
        return in_array($psnprofilesId, self::UNRELEASED_GAMES, true);
    }

    private function findGameByPsnprofilesId(int $psnprofilesId): ?PsnpPlusGame
    {
        $query = $this->database->prepare(
            'SELECT id, np_communication_id, `name`
            FROM trophy_title
            WHERE psnprofiles_id = :psnprofiles_id'
        );
        $query->bindValue(':psnprofiles_id', $psnprofilesId, PDO::PARAM_INT);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return PsnpPlusGame::fromArray($row);
    }

    /**
     * @return int[]
     */
    private function findTrophyOrderNumbers(string $npCommunicationId, bool $obtainableOnly): array
    {
        $sql = 'SELECT order_id + 1 FROM trophy WHERE np_communication_id = :np_communication_id';
        if ($obtainableOnly) {
            $sql .= ' AND `status` = 1';
        }
        $sql .= ' ORDER BY order_id';

        $statement = $this->database->prepare($sql);
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows === false ? [] : $rows);
    }

    /**
     * @param int[] $source
     * @param int[] $comparison
     * @return int[]
     */
    private function calculateDifference(array $source, array $comparison): array
    {
        if ($source === []) {
            return [];
        }

        return array_values(array_diff($source, $comparison));
    }

    /**
     * @param string[] $foundNpCommunicationIds
     * @return PsnpPlusFixedGame[]
     */
    private function buildFixedGames(array $foundNpCommunicationIds): array
    {
        $allGames = $this->findGamesWithUnobtainableTrophies();
        $missing = array_diff($allGames, $foundNpCommunicationIds);

        $fixedGames = [];
        foreach ($missing as $npCommunicationId) {
            $game = $this->findGameByNpCommunicationId($npCommunicationId);
            if ($game === null) {
                continue;
            }

            $trophyIds = $this->findObtainableTrophyIds($npCommunicationId);
            $fixedGames[] = new PsnpPlusFixedGame($game->getId(), $game->getName(), $trophyIds);
        }

        return $fixedGames;
    }

    /**
     * @return string[]
     */
    private function findGamesWithUnobtainableTrophies(): array
    {
        $statement = $this->database->prepare(
            "SELECT DISTINCT np_communication_id
            FROM trophy
            WHERE `status` = 1 AND np_communication_id LIKE 'N%'
            ORDER BY np_communication_id"
        );
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_map('strval', $rows === false ? [] : $rows);
    }

    private function findGameByNpCommunicationId(string $npCommunicationId): ?PsnpPlusGame
    {
        $query = $this->database->prepare(
            'SELECT id, np_communication_id, `name`
            FROM trophy_title
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $row = $query->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return PsnpPlusGame::fromArray($row);
    }

    /**
     * @param int[] $orders
     * @return int[]
     */
    private function findTrophyIdsForOrders(string $npCommunicationId, array $orders): array
    {
        if ($orders === []) {
            return [];
        }

        $zeroBasedOrders = array_map(static fn (int $order): int => $order - 1, $orders);
        $placeholders = implode(',', array_fill(0, count($zeroBasedOrders), '?'));

        $sql = sprintf(
            'SELECT order_id, id FROM trophy WHERE np_communication_id = ? AND order_id IN (%s)',
            $placeholders
        );

        $statement = $this->database->prepare($sql);
        $statement->bindValue(1, $npCommunicationId, PDO::PARAM_STR);

        foreach ($zeroBasedOrders as $index => $order) {
            $statement->bindValue($index + 2, $order, PDO::PARAM_INT);
        }

        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $idsByOrder = [];
        foreach ($rows as $row) {
            if (!isset($row['order_id'], $row['id'])) {
                continue;
            }

            $order = (int) $row['order_id'];
            $idsByOrder[$order + 1] = (int) $row['id'];
        }

        $trophyIds = [];
        foreach ($orders as $order) {
            if (isset($idsByOrder[$order])) {
                $trophyIds[] = $idsByOrder[$order];
            }
        }

        return $trophyIds;
    }

    /**
     * @return int[]
     */
    private function findObtainableTrophyIds(string $npCommunicationId): array
    {
        $statement = $this->database->prepare(
            'SELECT id FROM trophy WHERE np_communication_id = :np_communication_id AND `status` = 1 ORDER BY id'
        );
        $statement->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows === false ? [] : $rows);
    }
}
