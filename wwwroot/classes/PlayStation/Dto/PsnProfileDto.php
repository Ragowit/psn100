<?php

declare(strict_types=1);

final class PsnProfileDto
{
    public function __construct(
        private readonly string $accountId,
        private readonly string $onlineId,
        private readonly string $currentOnlineId,
        private readonly string $npId
    ) {
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function onlineId(): string
    {
        return $this->onlineId;
    }

    public function currentOnlineId(): string
    {
        return $this->currentOnlineId;
    }

    public function npId(): string
    {
        return $this->npId;
    }
}
