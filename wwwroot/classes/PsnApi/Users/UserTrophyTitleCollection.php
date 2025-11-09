<?php

declare(strict_types=1);

namespace Achievements\PsnApi\Users;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

final class UserTrophyTitleCollection implements IteratorAggregate
{
    /** @var list<UserTrophyTitle> */
    private array $titles;

    /**
     * @param list<UserTrophyTitle> $titles
     */
    public function __construct(array $titles)
    {
        $this->titles = $titles;
    }

    /**
     * @return Traversable<UserTrophyTitle>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->titles);
    }
}
