<?php

declare(strict_types=1);

final class DeletePlayerConfirmation
{
    private string $accountId;

    private ?string $onlineId;

    public function __construct(string $accountId, ?string $onlineId)
    {
        $this->accountId = $accountId;
        $this->onlineId = $onlineId;
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
