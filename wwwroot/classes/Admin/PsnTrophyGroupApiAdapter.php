<?php

declare(strict_types=1);

final class PsnTrophyGroupApiAdapter
{
    /**
     * @param array<string, mixed> $rawGroup
     */
    public function __construct(
        private readonly string $groupId,
        private readonly array $rawGroup,
    ) {
    }

    public function id(): string
    {
        return $this->groupId;
    }

    public function name(): string
    {
        return (string) ($this->rawGroup['trophyGroupName'] ?? '');
    }

    public function detail(): string
    {
        return (string) ($this->rawGroup['trophyGroupDetail'] ?? '');
    }

    public function iconUrl(): string
    {
        return (string) ($this->rawGroup['trophyGroupIconUrl'] ?? '');
    }
}
