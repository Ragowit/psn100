<?php

declare(strict_types=1);

final readonly class DeletePlayerConfirmation
{
    public function __construct(final private string $accountId, final private ?string $onlineId)
    {
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getOnlineId(): ?string
    {
        return $this->onlineId;
    }
}
