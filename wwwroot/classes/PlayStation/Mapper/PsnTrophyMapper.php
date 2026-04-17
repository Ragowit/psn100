<?php

declare(strict_types=1);

require_once __DIR__ . '/../Dto/PsnTrophyDto.php';

final class PsnTrophyMapper
{
    /**
     * @param array<string, mixed> $rawTrophy
     */
    public function mapFromLookupArray(array $rawTrophy): PsnTrophyDto
    {
        $progressTarget = $rawTrophy['trophyProgressTargetValue'] ?? '';
        $rawTrophyId = $rawTrophy['trophyId'] ?? null;

        $rewardImageUrl = $rawTrophy['trophyRewardImageUrl'] ?? null;
        if (!is_string($rewardImageUrl) || $rewardImageUrl === '') {
            $rewardImageUrl = null;
        }

        return new PsnTrophyDto(
            (int) $rawTrophyId,
            is_numeric($rawTrophyId),
            (bool) ($rawTrophy['trophyHidden'] ?? false),
            strtolower((string) ($rawTrophy['trophyType'] ?? 'bronze')),
            (string) ($rawTrophy['trophyName'] ?? ''),
            (string) ($rawTrophy['trophyDetail'] ?? ''),
            (string) ($rawTrophy['trophyIconUrl'] ?? ''),
            (string) ($rawTrophy['earnedDateTime'] ?? ''),
            is_scalar($rawTrophy['progress'] ?? null) ? (string) $rawTrophy['progress'] : '',
            is_scalar($progressTarget) ? (string) $progressTarget : '',
            (string) ($rawTrophy['trophyRewardName'] ?? ''),
            $rewardImageUrl
        );
    }
}
