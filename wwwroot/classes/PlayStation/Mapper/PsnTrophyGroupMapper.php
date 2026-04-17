<?php

declare(strict_types=1);

require_once __DIR__ . '/../Dto/PsnTrophyGroupDto.php';
require_once __DIR__ . '/PsnTrophyMapper.php';

final class PsnTrophyGroupMapper
{
    public function __construct(
        private readonly ?PsnTrophyMapper $trophyMapper = null
    ) {
    }

    /**
     * @param array<string, mixed> $rawGroup
     */
    public function mapFromLookupArray(array $rawGroup): PsnTrophyGroupDto
    {
        $rawTrophies = $rawGroup['trophies'] ?? [];
        if (!is_array($rawTrophies)) {
            $rawTrophies = [];
        }

        $mapper = $this->trophyMapper ?? new PsnTrophyMapper();
        $trophies = [];

        foreach ($rawTrophies as $rawTrophy) {
            if (!is_array($rawTrophy)) {
                continue;
            }

            $trophies[] = $mapper->mapFromLookupArray($rawTrophy);
        }

        return new PsnTrophyGroupDto(
            (string) ($rawGroup['trophyGroupId'] ?? ''),
            (string) ($rawGroup['trophyGroupName'] ?? ''),
            (string) ($rawGroup['trophyGroupDetail'] ?? ''),
            (string) ($rawGroup['trophyGroupIconUrl'] ?? ''),
            $trophies
        );
    }
}
