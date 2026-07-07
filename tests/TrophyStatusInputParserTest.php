<?php

declare(strict_types=1);

require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../wwwroot/classes/Admin/TrophyStatusInputParser.php';

final class TrophyStatusInputParserTest extends TestCase
{
    public function testParseTrophyIdsAcceptsCommaSeparatedValues(): void
    {
        $parser = new TrophyStatusInputParser(new TrophyStatusInputParserPDO());

        $this->assertSame([1, 2, 3], $parser->parseTrophyIds('1,2,3'));
    }

    public function testParseTrophyIdsAcceptsWhitespaceSeparatedValues(): void
    {
        $parser = new TrophyStatusInputParser(new TrophyStatusInputParserPDO());

        $this->assertSame([10, 20, 30], $parser->parseTrophyIds("10\n20 30"));
    }

    public function testParseTrophyIdsDeduplicatesValues(): void
    {
        $parser = new TrophyStatusInputParser(new TrophyStatusInputParserPDO());

        $this->assertSame([5, 6], $parser->parseTrophyIds('5,6,5,6'));
    }

    public function testParseTrophyIdsTrimsSurroundingWhitespace(): void
    {
        $parser = new TrophyStatusInputParser(new TrophyStatusInputParserPDO());

        $this->assertSame([42], $parser->parseTrophyIds('  42  '));
    }

    public function testParseTrophyIdsRejectsNonNumericValues(): void
    {
        $parser = new TrophyStatusInputParser(new TrophyStatusInputParserPDO());

        try {
            $parser->parseTrophyIds('1,abc');
            $this->fail('Expected InvalidArgumentException for non-numeric trophy ID.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Invalid trophy ID: abc', $exception->getMessage());
        }
    }

    public function testParseTrophyIdsRejectsEmptyInput(): void
    {
        $parser = new TrophyStatusInputParser(new TrophyStatusInputParserPDO());

        try {
            $parser->parseTrophyIds('   ');
            $this->fail('Expected InvalidArgumentException for empty trophy input.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('No trophies were provided.', $exception->getMessage());
        }
    }

    public function testGetTrophyIdsForGameReturnsUniqueIds(): void
    {
        $database = new TrophyStatusInputParserPDO();
        $database->trophyIdsForGame = ['7', '8', '7'];
        $parser = new TrophyStatusInputParser($database);

        $this->assertSame([7, 8], $parser->getTrophyIdsForGame(99));
    }

    public function testGetTrophyIdsForGameRejectsMissingTrophies(): void
    {
        $database = new TrophyStatusInputParserPDO();
        $database->trophyIdsForGame = [];
        $parser = new TrophyStatusInputParser($database);

        try {
            $parser->getTrophyIdsForGame(99);
            $this->fail('Expected InvalidArgumentException when no trophies exist for the game.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('No trophies found for the selected game.', $exception->getMessage());
        }
    }
}

final class TrophyStatusInputParserPDO extends PDO
{
    /** @var list<string> */
    public array $trophyIdsForGame = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement
    {
        return new TrophyStatusInputParserStatement($this->trophyIdsForGame);
    }
}

final class TrophyStatusInputParserStatement extends PDOStatement
{
    /**
     * @param list<string> $trophyIdsForGame
     */
    public function __construct(private array $trophyIdsForGame)
    {
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return true;
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $this->trophyIdsForGame;
    }
}
