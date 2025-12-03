<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/TrophyMergeService.php';

final class TrophyMergeServiceNameMergeTest extends TestCase
{
    public function testInsertMappingsByNameTrimsWhitespaceBeforeMatching(): void
    {
        $childTrophies = [
            [
                'np_communication_id' => 'NP_CHILD',
                'group_id' => 'default',
                'order_id' => 5,
                'name' => ' Trophy A ',
            ],
        ];

        $parentTrophy = [
            'np_communication_id' => 'MERGE_000001',
            'group_id' => 'default',
            'order_id' => 7,
            'name' => 'Trophy A',
        ];

        $database = new NameMappingPDO($childTrophies, $parentTrophy);
        $service = new TrophyMergeService($database);

        $method = new ReflectionMethod(TrophyMergeService::class, 'insertMappingsByName');
        $method->setAccessible(true);

        $message = $method->invoke($service, 1, 2);

        $this->assertSame('', $message, 'Expected name-based merge to succeed without warnings.');

        $this->assertSame([
            [
                ':child_np_communication_id' => 'NP_CHILD',
                ':child_group_id' => 'default',
                ':child_order_id' => 5,
                ':parent_np_communication_id' => 'MERGE_000001',
                ':parent_group_id' => 'default',
                ':parent_order_id' => 7,
            ],
        ], $database->mappings, 'Expected whitespace differences to be ignored during name matching.');
    }
}

final class NameMappingPDO extends PDO
{
    /** @var list<array{np_communication_id:string, group_id:string, order_id:int, name:string}> */
    private array $childTrophies;

    /** @var array{np_communication_id:string, group_id:string, order_id:int, name:string} */
    private array $parentTrophy;

    /** @var list<array<string, scalar>> */
    public array $mappings = [];

    /**
     * @param list<array{np_communication_id:string, group_id:string, order_id:int, name:string}> $childTrophies
     * @param array{np_communication_id:string, group_id:string, order_id:int, name:string} $parentTrophy
     */
    public function __construct(array $childTrophies, array $parentTrophy)
    {
        $this->childTrophies = $childTrophies;
        $this->parentTrophy = $parentTrophy;
    }

    public function prepare(string $statement, array $options = []): PDOStatement
    {
        $normalized = preg_replace('/\s+/', ' ', trim($statement)) ?? '';

        if (str_contains($normalized, 'FROM trophy WHERE np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :child_game_id)')) {
            return new ChildTrophyStatement($this->childTrophies);
        }

        if (str_contains($normalized, 'FROM trophy WHERE np_communication_id = (SELECT np_communication_id FROM trophy_title WHERE id = :parent_game_id)') && str_contains($normalized, 'TRIM(`name`) = :name')) {
            return new ParentTrophyStatement($this->parentTrophy);
        }

        if (str_starts_with($normalized, 'INSERT IGNORE into trophy_merge')) {
            return new InsertMappingStatement($this);
        }

        throw new RuntimeException('Unexpected SQL statement: ' . $statement);
    }

    /** @param array<string, scalar> $mapping */
    public function recordMapping(array $mapping): void
    {
        $this->mappings[] = $mapping;
    }
}

final class ChildTrophyStatement extends PDOStatement
{
    /** @var list<array{np_communication_id:string, group_id:string, order_id:int, name:string}> */
    private array $childTrophies;

    private int $index = 0;

    /** @param list<array{np_communication_id:string, group_id:string, order_id:int, name:string}> $childTrophies */
    public function __construct(array $childTrophies)
    {
        $this->childTrophies = $childTrophies;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        // No-op for testing purposes.

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->index = 0;

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): array|false
    {
        if (!isset($this->childTrophies[$this->index])) {
            return false;
        }

        return $this->childTrophies[$this->index++];
    }
}

final class ParentTrophyStatement extends PDOStatement
{
    /** @var array{np_communication_id:string, group_id:string, order_id:int, name:string} */
    private array $parentTrophy;

    /** @var array<string, scalar> */
    private array $parameters = [];

    /** @param array{np_communication_id:string, group_id:string, order_id:int, name:string} $parentTrophy */
    public function __construct(array $parentTrophy)
    {
        $this->parentTrophy = $parentTrophy;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[(string) $param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (($this->parameters[':name'] ?? null) !== $this->parentTrophy['name']) {
            return [];
        }

        return [$this->parentTrophy];
    }
}

final class InsertMappingStatement extends PDOStatement
{
    private NameMappingPDO $database;

    /** @var array<string, scalar> */
    private array $parameters = [];

    public function __construct(NameMappingPDO $database)
    {
        $this->database = $database;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->parameters[(string) $param] = $value;

        return true;
    }

    public function execute(?array $params = null): bool
    {
        $this->database->recordMapping($this->parameters);

        return true;
    }
}
