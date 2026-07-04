<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyCalculator.php';

final class TrophyCalculatorTest extends TestCase
{
    private FakePDO $database;
    private TrophyCalculator $calculator;

    protected function setUp(): void
    {
        $this->database = new FakePDO();
        $this->calculator = new TrophyCalculator($this->database);
    }

    public function testRecalculateTrophyGroupEnsuresMinimumProgress(): void
    {
        $npCommunicationId = 'NPWR99999';
        $groupId = '000';
        $accountId = 42;

        $this->database->setTrophyGroup($npCommunicationId, $groupId);

        for ($order = 1; $order <= 200; $order++) {
            $this->database->addTrophy($npCommunicationId, $groupId, $order, 'bronze');
        }

        $this->database->addEarnedTrophy($npCommunicationId, $groupId, 1, $accountId);

        $this->calculator->recalculateTrophyGroup($npCommunicationId, $groupId, $accountId);

        $group = $this->database->getTrophyGroup($npCommunicationId, $groupId);
        $this->assertSame(200, $group['bronze']);
        $this->assertSame(0, $group['silver']);
        $this->assertSame(0, $group['gold']);
        $this->assertSame(0, $group['platinum']);

        $playerProgress = $this->database->getTrophyGroupPlayer($npCommunicationId, $groupId, $accountId);
        $this->assertSame(1, $playerProgress['progress']);
        $this->assertSame(1, $playerProgress['bronze']);
        $this->assertSame(0, $playerProgress['silver']);
        $this->assertSame(0, $playerProgress['gold']);
        $this->assertSame(0, $playerProgress['platinum']);
    }

    public function testRecalculateTrophyGroupCapsProgressAtNinetyNineWithoutPlatinum(): void
    {
        $npCommunicationId = 'NPWR88888';
        $groupId = '000';
        $accountId = 99;

        $this->database->setTrophyGroup($npCommunicationId, $groupId);

        $this->database->addTrophy($npCommunicationId, $groupId, 1, 'bronze');
        $this->database->addTrophy($npCommunicationId, $groupId, 2, 'silver');
        $this->database->addTrophy($npCommunicationId, $groupId, 3, 'gold');
        $this->database->addTrophy($npCommunicationId, $groupId, 4, 'platinum');

        $this->database->addEarnedTrophy($npCommunicationId, $groupId, 1, $accountId);
        $this->database->addEarnedTrophy($npCommunicationId, $groupId, 2, $accountId);
        $this->database->addEarnedTrophy($npCommunicationId, $groupId, 3, $accountId);

        $this->calculator->recalculateTrophyGroup($npCommunicationId, $groupId, $accountId);

        $group = $this->database->getTrophyGroup($npCommunicationId, $groupId);
        $this->assertSame(1, $group['bronze']);
        $this->assertSame(1, $group['silver']);
        $this->assertSame(1, $group['gold']);
        $this->assertSame(1, $group['platinum']);

        $playerProgress = $this->database->getTrophyGroupPlayer($npCommunicationId, $groupId, $accountId);
        $this->assertSame(99, $playerProgress['progress']);
        $this->assertSame(1, $playerProgress['bronze']);
        $this->assertSame(1, $playerProgress['silver']);
        $this->assertSame(1, $playerProgress['gold']);
        $this->assertSame(0, $playerProgress['platinum']);
    }

    public function testRecalculateTrophyGroupWithZeroMaxScoreSetsFullProgress(): void
    {
        $npCommunicationId = 'NPWR77777';
        $groupId = '100';
        $accountId = 7;

        $this->database->setTrophyGroup($npCommunicationId, $groupId);

        $this->database->addTrophy($npCommunicationId, $groupId, 1, 'platinum');

        $this->calculator->recalculateTrophyGroup($npCommunicationId, $groupId, $accountId);

        $group = $this->database->getTrophyGroup($npCommunicationId, $groupId);
        $this->assertSame(0, $group['bronze']);
        $this->assertSame(0, $group['silver']);
        $this->assertSame(0, $group['gold']);
        $this->assertSame(1, $group['platinum']);

        $playerProgress = $this->database->getTrophyGroupPlayer($npCommunicationId, $groupId, $accountId);
        $this->assertSame(100, $playerProgress['progress']);
        $this->assertSame(0, $playerProgress['bronze']);
        $this->assertSame(0, $playerProgress['silver']);
        $this->assertSame(0, $playerProgress['gold']);
        $this->assertSame(0, $playerProgress['platinum']);
    }
}

final class FakePDO extends PDO
{
    /** @var list<array{np_communication_id:string,group_id:string,order_id:int,type:string,status:int}> */
    public array $trophies = [];

    /**
     * @var array<string, array{np_communication_id:string,group_id:string,bronze:int,silver:int,gold:int,platinum:int}>
     */
    public array $trophyGroups = [];

    /** @var list<array{np_communication_id:string,group_id:string,order_id:int,account_id:int,earned:int}> */
    public array $trophyEarned = [];

    /**
     * @var array<string, array{np_communication_id:string,group_id:string,account_id:int,bronze:int,silver:int,gold:int,platinum:int,progress:int}>
     */
    public array $trophyGroupPlayers = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): FakePDOStatement
    {
        return new FakePDOStatement($this, $query);
    }

    public function addTrophy(string $npCommunicationId, string $groupId, int $orderId, string $type, int $status = 0): void
    {
        $this->trophies[] = [
            'np_communication_id' => $npCommunicationId,
            'group_id' => $groupId,
            'order_id' => $orderId,
            'type' => $type,
            'status' => $status,
        ];
    }

    public function setTrophyGroup(string $npCommunicationId, string $groupId): void
    {
        $this->trophyGroups[$this->buildGroupKey($npCommunicationId, $groupId)] = [
            'np_communication_id' => $npCommunicationId,
            'group_id' => $groupId,
            'bronze' => 0,
            'silver' => 0,
            'gold' => 0,
            'platinum' => 0,
        ];
    }

    public function addEarnedTrophy(string $npCommunicationId, string $groupId, int $orderId, int $accountId, int $earned = 1): void
    {
        $this->trophyEarned[] = [
            'np_communication_id' => $npCommunicationId,
            'group_id' => $groupId,
            'order_id' => $orderId,
            'account_id' => $accountId,
            'earned' => $earned,
        ];
    }

    /**
     * @return array{np_communication_id:string,group_id:string,bronze:int,silver:int,gold:int,platinum:int}
     */
    public function getTrophyGroup(string $npCommunicationId, string $groupId): array
    {
        return $this->trophyGroups[$this->buildGroupKey($npCommunicationId, $groupId)];
    }

    /**
     * @return array{np_communication_id:string,group_id:string,account_id:int,bronze:int,silver:int,gold:int,platinum:int,progress:int}|null
     */
    public function getTrophyGroupPlayer(string $npCommunicationId, string $groupId, int $accountId): ?array
    {
        return $this->trophyGroupPlayers[$this->buildGroupPlayerKey($npCommunicationId, $groupId, $accountId)] ?? null;
    }

    public function updateTrophyGroupCounts(string $npCommunicationId, string $groupId, int $bronze, int $silver, int $gold, int $platinum): void
    {
        $key = $this->buildGroupKey($npCommunicationId, $groupId);

        if (!isset($this->trophyGroups[$key])) {
            $this->setTrophyGroup($npCommunicationId, $groupId);
        }

        $this->trophyGroups[$key]['bronze'] = $bronze;
        $this->trophyGroups[$key]['silver'] = $silver;
        $this->trophyGroups[$key]['gold'] = $gold;
        $this->trophyGroups[$key]['platinum'] = $platinum;
    }

    public function saveTrophyGroupPlayer(string $npCommunicationId, string $groupId, int $accountId, int $bronze, int $silver, int $gold, int $platinum, int $progress): void
    {
        $this->trophyGroupPlayers[$this->buildGroupPlayerKey($npCommunicationId, $groupId, $accountId)] = [
            'np_communication_id' => $npCommunicationId,
            'group_id' => $groupId,
            'account_id' => $accountId,
            'bronze' => $bronze,
            'silver' => $silver,
            'gold' => $gold,
            'platinum' => $platinum,
            'progress' => $progress,
        ];
    }

    public function findTrophy(string $npCommunicationId, string $groupId, int $orderId): ?array
    {
        foreach ($this->trophies as $trophy) {
            if (
                $trophy['np_communication_id'] === $npCommunicationId
                && $trophy['group_id'] === $groupId
                && $trophy['order_id'] === $orderId
            ) {
                return $trophy;
            }
        }

        return null;
    }

    private function buildGroupKey(string $npCommunicationId, string $groupId): string
    {
        return $npCommunicationId . '|' . $groupId;
    }

    private function buildGroupPlayerKey(string $npCommunicationId, string $groupId, int $accountId): string
    {
        return $npCommunicationId . '|' . $groupId . '|' . $accountId;
    }
}

final class FakePDOStatement extends PDOStatement
{
    private FakePDO $database;

    private string $query;

    /** @var array<string, mixed> */
    private array $parameters = [];

    /** @var array<mixed> */
    private array $result = [];

    public function __construct(FakePDO $database, string $query)
    {
        $this->database = $database;
        $this->query = $query;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[$param] = $value;

        return true;
    }

    /**
     * @param array<string, mixed>|null $params
     */
    public function execute(?array $params = null): bool
    {
        if ($params !== null) {
            foreach ($params as $key => $value) {
                $this->parameters[$key] = $value;
            }
        }

        $this->result = [];
        $normalizedQuery = trim($this->query);

        if (str_starts_with($normalizedQuery, 'SELECT t.type, COUNT(*) AS count')) {
            $this->executeSelectTrophyCounts();
            return true;
        }

        if (str_starts_with($normalizedQuery, 'UPDATE trophy_group')) {
            $this->executeUpdateTrophyGroup();
            return true;
        }

        if (str_starts_with($normalizedQuery, 'SELECT t.type, COUNT(t.type) AS count')) {
            $this->executeSelectEarnedCounts();
            return true;
        }

        if (str_starts_with($normalizedQuery, 'INSERT INTO trophy_group_player')) {
            $this->executeSaveTrophyGroupPlayer();
            return true;
        }

        throw new RuntimeException('Unhandled query: ' . $this->query);
    }

    /**
     * @return array<mixed>
     */
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if ($mode === PDO::FETCH_KEY_PAIR) {
            return $this->result;
        }

        return $this->result;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        if ($this->result === []) {
            return false;
        }

        $row = reset($this->result);

        return $row === false ? false : $row;
    }

    private function executeSelectTrophyCounts(): void
    {
        $npCommunicationId = (string) $this->parameters[':np_communication_id'];
        $groupId = (string) $this->parameters[':group_id'];

        $counts = [];

        foreach ($this->database->trophies as $trophy) {
            if (
                $trophy['np_communication_id'] === $npCommunicationId
                && $trophy['group_id'] === $groupId
                && $trophy['status'] === 0
            ) {
                $type = $trophy['type'];
                $counts[$type] = ($counts[$type] ?? 0) + 1;
            }
        }

        $this->result = $counts;
    }

    private function executeUpdateTrophyGroup(): void
    {
        $this->database->updateTrophyGroupCounts(
            (string) $this->parameters[':np_communication_id'],
            (string) $this->parameters[':group_id'],
            (int) $this->parameters[':bronze'],
            (int) $this->parameters[':silver'],
            (int) $this->parameters[':gold'],
            (int) $this->parameters[':platinum']
        );
    }

    private function executeSelectEarnedCounts(): void
    {
        $npCommunicationId = (string) $this->parameters[':np_communication_id'];
        $groupId = (string) $this->parameters[':group_id'];
        $accountId = (int) $this->parameters[':account_id'];

        $counts = [];

        foreach ($this->database->trophyEarned as $earned) {
            if (
                $earned['np_communication_id'] !== $npCommunicationId
                || $earned['group_id'] !== $groupId
                || $earned['account_id'] !== $accountId
                || $earned['earned'] !== 1
            ) {
                continue;
            }

            $trophy = $this->database->findTrophy($npCommunicationId, $groupId, $earned['order_id']);

            if ($trophy === null || $trophy['status'] !== 0) {
                continue;
            }

            $type = $trophy['type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        $this->result = $counts;
    }

    private function executeSaveTrophyGroupPlayer(): void
    {
        $this->database->saveTrophyGroupPlayer(
            (string) $this->parameters[':np_communication_id'],
            (string) $this->parameters[':group_id'],
            (int) $this->parameters[':account_id'],
            (int) $this->parameters[':bronze'],
            (int) $this->parameters[':silver'],
            (int) $this->parameters[':gold'],
            (int) $this->parameters[':platinum'],
            (int) $this->parameters[':progress']
        );
    }
}
