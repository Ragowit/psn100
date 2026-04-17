<?php

declare(strict_types=1);

interface UserSearchClientInterface
{
    /**
     * @return iterable<object>
     */
    public function searchUsers(string $onlineId): iterable;
}
