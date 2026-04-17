<?php

declare(strict_types=1);

interface ProfileClientInterface
{
    public function lookupProfileByOnlineId(string $onlineId): mixed;

    public function findUserByAccountId(string $accountId): object;
}
