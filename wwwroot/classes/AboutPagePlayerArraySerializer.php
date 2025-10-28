<?php

declare(strict_types=1);

require_once __DIR__ . '/AboutPagePlayer.php';

final class AboutPagePlayerArraySerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function serialize(AboutPagePlayer $player): array
    {
        return [
            'onlineId' => $player->getOnlineId(),
            'countryCode' => $player->getCountryCode(),
            'countryName' => $player->getCountryName(),
            'avatarUrl' => $player->getAvatarUrl(),
            'lastUpdatedDate' => $player->getLastUpdatedDate(),
            'isRanked' => $player->isRanked(),
            'ranking' => $player->getRanking(),
            'hasHiddenTrophies' => $player->hasHiddenTrophies(),
            'statusLabel' => $player->getStatusLabel(),
            'isNew' => $player->isNew(),
            'rankDeltaLabel' => $player->getRankDeltaLabel(),
            'rankDeltaColor' => $player->getRankDeltaColor(),
            'progress' => $player->getProgress(),
            'level' => $player->getLevel(),
            'status' => $player->getStatus(),
        ];
    }

    /**
     * @param array<int, AboutPagePlayer> $players
     *
     * @return array<int, array<string, mixed>>
     */
    public static function serializeCollection(array $players): array
    {
        $serializedPlayers = [];

        foreach ($players as $player) {
            if (!$player instanceof AboutPagePlayer) {
                continue;
            }

            $serializedPlayers[] = self::serialize($player);
        }

        return $serializedPlayers;
    }
}
