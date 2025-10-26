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
                '(games.name LIKE :search_prefix_0 OR games.name LIKE :search_prefix_1) AS prefix_match',
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
                '(games.name = :search_fulltext_0) AS exact_match',
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
            '(((MATCH(games.name) AGAINST (:search_fulltext_0)) > 0 OR (MATCH(games.name) AGAINST (:search_fulltext_1)) > 0) OR (games.name LIKE :search_like_0 OR games.name LIKE :search_like_1))',
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
}
