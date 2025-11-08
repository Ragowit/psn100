<?php

declare(strict_types=1);

namespace PsnApi;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, TrophyTitle>
 */
final class TrophyTitleCollection implements IteratorAggregate, Countable
{
    /** @var list<TrophyTitle> */
    private array $titles;

    /**
     * @param list<TrophyTitle> $titles
     */
    public function __construct(array $titles)
    {
        $this->titles = array_values($titles);
    }

    /**
     * @return Traversable<int, TrophyTitle>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->titles);
    }

    public function count(): int
    {
        return count($this->titles);
    }
}
