<?php

declare(strict_types=1);

require_once __DIR__ . '/PlayStationWorkerAuthenticator.php';

/**
 * PSN lookups and worker authentication used during admin game rescans.
 *
 * Extracted from GameRescanService so rescan orchestration stays separate from
 * database title resolution and PlayStation API player discovery.
 */
final class GameRescanPsnAccessor
{
    private const string ORIGINAL_GAME_PREFIX = 'NPWR';
    private const int LOGIN_RETRY_DELAY_SECONDS = 300;
    private const int ACCESSIBLE_PLAYER_PROBE_BATCH_SIZE = 100;

    public function __construct(
        private readonly PDO $database,
        private readonly PlayStationWorkerAuthenticator $workerAuthenticator,
    ) {
    }

    public function getGameNpCommunicationId(int $gameId): string
    {
        $query = $this->database->prepare('SELECT np_communication_id FROM trophy_title WHERE id = :id');
        $query->bindValue(':id', $gameId, PDO::PARAM_INT);
        $query->execute();

        $npCommunicationId = $query->fetchColumn();
        if ($npCommunicationId === false) {
            throw new RuntimeException('Unable to find the specified game.');
        }

        return (string) $npCommunicationId;
    }

    public function isOriginalGame(string $npCommunicationId): bool
    {
        return str_starts_with($npCommunicationId, self::ORIGINAL_GAME_PREFIX);
    }

    /**
     * @param callable(int, Throwable): void|null $onLoginFailure
     */
    public function loginToWorker(?callable $onLoginFailure = null): object
    {
        return $this->workerAuthenticator->authenticateWithRetry(
            self::LOGIN_RETRY_DELAY_SECONDS,
            $onLoginFailure,
        );
    }

    public function findAccessibleUserWithGame(object $client, string $npCommunicationId): ?object
    {
        // Page owners in batches (idx_npcid_lupdate serves the sort) so popular
        // titles do not hold one unbounded result set open, while still scanning
        // past clusters of recent private profiles until a public owner is found.
        $offset = 0;

        while (true) {
            $query = $this->database->prepare(
                'SELECT account_id
                FROM trophy_title_player ttp
                JOIN player p USING(account_id)
                WHERE ttp.np_communication_id = :np_communication_id
                ORDER BY ttp.last_updated_date DESC, ttp.account_id DESC
                LIMIT ' . self::ACCESSIBLE_PLAYER_PROBE_BATCH_SIZE . ' OFFSET ' . $offset
            );
            $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
            $query->execute();

            $batchCount = 0;
            while (($accountId = $query->fetchColumn()) !== false) {
                $batchCount++;
                $user = $client->users()->find((string) $accountId);

                try {
                    $user->trophySummary()->level();

                    return $user;
                } catch (TypeError) {
                    // Something odd, try next player.
                } catch (Exception) {
                    // Player probably private, try next player.
                }
            }

            if ($batchCount < self::ACCESSIBLE_PLAYER_PROBE_BATCH_SIZE) {
                return null;
            }

            $offset += self::ACCESSIBLE_PLAYER_PROBE_BATCH_SIZE;
        }
    }

    public function findTrophyTitleForUser(object $user, string $npCommunicationId): ?object
    {
        foreach ($user->trophyTitles() as $trophyTitle) {
            if ($trophyTitle->npCommunicationId() === $npCommunicationId) {
                return $trophyTitle;
            }
        }

        return null;
    }
}
