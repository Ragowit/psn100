<?php

declare(strict_types=1);

require_once __DIR__ . '/../wwwroot/classes/SearchQueryHelper.php';

final class SearchQueryHelperTest extends TestCase
{
    public function testAddFulltextSelectColumnsIncludesVariantsAndScore(): void
    {
        $helper = new SearchQueryHelper();

        $columns = $helper->addFulltextSelectColumns(
            [],
            'games.name',
            true,
            'Final Fantasy 7'
        );

        $this->assertSame(
            [
                '(games.name = :search_fulltext_0 OR games.name = :search_fulltext_1) AS exact_match',
                '((MATCH(games.name) AGAINST (:search_boolean_0 IN BOOLEAN MODE)) > 0 OR (MATCH(games.name) AGAINST (:search_boolean_1 IN BOOLEAN MODE)) > 0) AS prefix_match',
                'GREATEST(MATCH(games.name) AGAINST (:search_fulltext_0), MATCH(games.name) AGAINST (:search_fulltext_1)) AS score',
            ],
            $columns
        );
    }

    public function testAddFulltextSelectColumnsSkipsWhenScoreNotRequested(): void
    {
        $helper = new SearchQueryHelper();

        $originalColumns = ['games.id'];
        $columns = $helper->addFulltextSelectColumns(
            $originalColumns,
            'games.name',
            false,
            'Resident Evil 4'
        );

        $this->assertSame($originalColumns, $columns);
    }

    public function testAddFulltextSelectColumnsHandlesEmptySearchTerm(): void
    {
        $helper = new SearchQueryHelper();

        $columns = $helper->addFulltextSelectColumns(
            [],
            'games.name',
            true,
            ''
        );

        $this->assertSame(
            [
                '0 AS exact_match',
                '0 AS prefix_match',
                'MATCH(games.name) AGAINST (:search_fulltext_0) AS score',
            ],
            $columns
        );
    }

    public function testAppendFulltextConditionBuildsClauseForVariants(): void
    {
        $helper = new SearchQueryHelper();

        $conditions = $helper->appendFulltextCondition(
            [],
            true,
            'games.name',
            'Final Fantasy VII'
        );

        $this->assertSame(1, count($conditions));

        $this->assertSame(
            '(((MATCH(games.name) AGAINST (:search_fulltext_0)) > 0 OR (MATCH(games.name) AGAINST (:search_fulltext_1)) > 0) OR ((MATCH(games.name) AGAINST (:search_boolean_0 IN BOOLEAN MODE)) > 0 OR (MATCH(games.name) AGAINST (:search_boolean_1 IN BOOLEAN MODE)) > 0))',
            $conditions[0]
        );
    }

    public function testAppendFulltextConditionSkipsWhenNotApplicable(): void
    {
        $helper = new SearchQueryHelper();

        $originalConditions = ['games.is_active = 1'];
        $conditions = $helper->appendFulltextCondition(
            $originalConditions,
            false,
            'games.name',
            'Final Fantasy VII'
        );

        $this->assertSame($originalConditions, $conditions);
    }

    public function testBindSearchParametersDoesNotBindRemovedPrefixPlaceholders(): void
    {
        $helper = new SearchQueryHelper();
        $statement = new RecordingSearchStatement();

        $helper->bindSearchParameters($statement, 'Final Fantasy 7', true);

        $this->assertSame(
            [
                ':search_fulltext_0' => 'Final Fantasy 7',
                ':search_fulltext_1' => 'Final Fantasy VII',
                ':search_boolean_0' => 'Final Fantasy 7*',
                ':search_boolean_1' => 'Final Fantasy VII*',
            ],
            $statement->bindings
        );
    }

    public function testBindSearchParametersBindsEmptyPlaceholderForEmptySearchTerm(): void
    {
        $helper = new SearchQueryHelper();
        $statement = new RecordingSearchStatement();

        $helper->bindSearchParameters($statement, '', false);

        $this->assertSame(
            [
                ':search_fulltext_0' => '',
            ],
            $statement->bindings
        );
    }
}

final class RecordingSearchStatement extends PDOStatement
{
    /** @var array<string, string> */
    public array $bindings = [];

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $this->bindings[(string) $param] = (string) $value;

        return true;
    }
}
