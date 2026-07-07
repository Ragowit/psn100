<?php

declare(strict_types=1);

require_once __DIR__ . '/../PsnHttpExceptionClassifier.php';

use Tustin\Haste\Exception\NotFoundHttpException;
use Tustin\PlayStation\Client;

/**
 * Resolves PSN profile data, synchronizes avatars, and upserts player rows during scans.
 *
 * Encapsulates profile lookup, country resolution, avatar caching, and player persistence
 * that were previously embedded in ThirtyMinuteCronJob.
 */
final class PlayerScanProfileSynchronizer
{
    private const MAX_INVALID_API_RESPONSE_ATTEMPTS = 2;

    public function __construct(
        private readonly PDO $database,
        private readonly ImageHashCalculator $imageHashCalculator,
        private readonly Psn100Logger $logger,
        private readonly WorkerScanCoordinator $workerScanCoordinator,
        private readonly string $avatarStorageDirectory = '/home/psn100/public_html/img/avatar/',
    ) {
    }

    /**
     * @param array<string, mixed> $player
     */
    public function synchronizeProfile(
        Client $client,
        array $player,
        int $workerId,
        string $displayOnlineId,
    ): PlayerScanProfileSyncResult {
        $this->workerScanCoordinator->setWaitingScanProgress(
            $workerId,
            sprintf('Updating profile data for %s.', $displayOnlineId)
        );

        $resolved = $this->resolvePlayerForScan($client, $player, $workerId, $displayOnlineId);

        if ($resolved->shouldSkipPlayer()) {
            return $resolved;
        }

        $user = $resolved->user;
        $country = $resolved->country ?? 'zz';

        if ($user === null) {
            return PlayerScanProfileSyncResult::skipPlayer();
        }

        $this->workerScanCoordinator->setWaitingScanProgress(
            $workerId,
            sprintf('Updating avatar for %s.', $displayOnlineId)
        );

        $avatarFilename = $this->synchronizeAvatar($user);
        $this->upsertPlayerRecord($user, $country, $avatarFilename);

        return PlayerScanProfileSyncResult::success($resolved->player, $user, $country);
    }

    /**
     * @param array<string, mixed> $profile
     */
    public function determineResolvedOnlineId(array $profile, string $fallbackOnlineId): string
    {
        $currentOnlineId = $profile['currentOnlineId'] ?? null;
        if (is_string($currentOnlineId) && $currentOnlineId !== '') {
            return $currentOnlineId;
        }

        $onlineId = $profile['onlineId'] ?? null;
        if (is_string($onlineId) && $onlineId !== '') {
            return $onlineId;
        }

        return $fallbackOnlineId;
    }

    public function extractCountryFromNpId(mixed $npId): ?string
    {
        if (!is_string($npId) || $npId === '') {
            return null;
        }

        $decoded = base64_decode($npId, true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        $trimmed = trim($decoded);
        if ($trimmed === '') {
            return null;
        }

        if (strlen($trimmed) < 2) {
            return null;
        }

        return strtolower(substr($trimmed, -2));
    }

    public function normalizeAccountIdValue(mixed $accountId): ?string
    {
        if (is_int($accountId)) {
            return (string) $accountId;
        }

        if (is_string($accountId)) {
            $trimmed = trim($accountId);

            if ($trimmed === '') {
                return null;
            }

            return ctype_digit($trimmed) ? $trimmed : null;
        }

        if (is_float($accountId)) {
            return (string) (int) $accountId;
        }

        if (is_numeric($accountId)) {
            $numeric = (string) $accountId;

            return ctype_digit($numeric) ? $numeric : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $player
     */
    private function resolvePlayerForScan(
        Client $client,
        array $player,
        int $workerId,
        string $displayOnlineId,
    ): PlayerScanProfileSyncResult {
        for ($attempt = 1; $attempt <= self::MAX_INVALID_API_RESPONSE_ATTEMPTS; $attempt++) {
            try {
                $originalOnlineId = (string) $player['online_id'];
                $existingAccountId = $this->normalizeAccountIdValue($player['account_id'] ?? null);
                $profileLookup = $this->lookupPlayerProfile($client, $originalOnlineId);
                $country = 'zz';

                if ($profileLookup !== null) {
                    $profile = $profileLookup['profile'] ?? null;

                    if (!is_array($profile)) {
                        $this->markPlayerAsPrivate($originalOnlineId);

                        return PlayerScanProfileSyncResult::skipPlayer();
                    }

                    $profileAccountId = $profile['accountId'] ?? null;

                    if (!is_string($profileAccountId) || $profileAccountId === '') {
                        $this->markPlayerAsPrivate($originalOnlineId);

                        return PlayerScanProfileSyncResult::skipPlayer();
                    }

                    $resolvedOnlineId = $this->determineResolvedOnlineId($profile, $originalOnlineId);

                    if ($resolvedOnlineId !== '' && strcasecmp($resolvedOnlineId, $originalOnlineId) !== 0) {
                        $this->updateQueuedOnlineId($workerId, $originalOnlineId, $resolvedOnlineId);
                        $player['online_id'] = $resolvedOnlineId;
                    } else {
                        $player['online_id'] = $originalOnlineId;
                    }

                    $player['account_id'] = $profileAccountId;
                    $user = $client->users()->find($profileAccountId);

                    $countryFromProfile = $this->extractCountryFromNpId($profile['npId'] ?? null);
                    $country = $countryFromProfile;

                    if ($country === null || strtolower($country) === 'zz') {
                        $storedCountry = $this->fetchStoredCountryByAccountId((int) $profileAccountId);

                        if (is_string($storedCountry) && $storedCountry !== '') {
                            $country = $storedCountry;
                        } else {
                            $country = 'zz';
                        }

                        if (strtolower($country) === 'zz') {
                            $resolvedCountry = $this->findPlayerCountry($client, $user->onlineId());

                            if ($resolvedCountry !== null) {
                                $country = $resolvedCountry;
                                $this->updatePlayerCountry((int) $profileAccountId, $resolvedCountry);
                            }
                        }
                    } else {
                        $this->updatePlayerCountry((int) $profileAccountId, $country);
                    }
                } else {
                    if ($existingAccountId === null) {
                        $this->markPlayerAsPrivate($originalOnlineId);

                        return PlayerScanProfileSyncResult::skipPlayer();
                    }

                    $player['account_id'] = $existingAccountId;
                    $user = $client->users()->find($existingAccountId);

                    $resolvedOnlineId = (string) $user->onlineId();

                    if ($resolvedOnlineId !== '' && strcasecmp($resolvedOnlineId, $originalOnlineId) !== 0) {
                        $this->updateQueuedOnlineId($workerId, $originalOnlineId, $resolvedOnlineId);
                        $player['online_id'] = $resolvedOnlineId;
                    } else {
                        $player['online_id'] = $originalOnlineId;
                    }

                    $storedCountry = $this->fetchStoredCountryByAccountId((int) $existingAccountId);

                    if (is_string($storedCountry) && $storedCountry !== '') {
                        $country = $storedCountry;
                    }

                    if (strtolower($country) === 'zz') {
                        $resolvedCountry = $this->findPlayerCountry($client, $user->onlineId());

                        if ($resolvedCountry !== null) {
                            $country = $resolvedCountry;
                            $this->updatePlayerCountry((int) $existingAccountId, $resolvedCountry);
                        }
                    }
                }

                if (!is_string($country) || $country === '') {
                    $country = 'zz';
                }

                $user->aboutMe();

                if (strcasecmp($player['online_id'], $user->onlineId()) !== 0) {
                    $this->updateQueuedOnlineId($workerId, (string) $player['online_id'], $user->onlineId());
                    $player['online_id'] = $user->onlineId();
                }

                return PlayerScanProfileSyncResult::success($player, $user, $country);
            } catch (TypeError $exception) {
                if ($attempt < self::MAX_INVALID_API_RESPONSE_ATTEMPTS) {
                    $this->pauseBeforeRetryingInvalidApiResponse($workerId, $displayOnlineId);

                    continue;
                }

                $this->handleInvalidApiResponse($player, $workerId, $exception);

                return PlayerScanProfileSyncResult::skipPlayer();
            } catch (Exception $exception) {
                $this->handlePlayerNotFoundDuringProfileSync($player, $exception);

                return PlayerScanProfileSyncResult::skipPlayer();
            } catch (Throwable $exception) {
                if ($attempt < self::MAX_INVALID_API_RESPONSE_ATTEMPTS) {
                    $this->pauseBeforeRetryingInvalidApiResponse($workerId, $displayOnlineId);

                    continue;
                }

                $this->handleInvalidApiResponse($player, $workerId, $exception);

                return PlayerScanProfileSyncResult::skipPlayer();
            }
        }

        return PlayerScanProfileSyncResult::skipPlayer();
    }

    private function synchronizeAvatar(object $user): string
    {
        $avatarUrls = $user->avatarUrls();
        $avatarFilename = '';

        for ($i = 0; $i < 4; $i++) {
            switch ($i) {
                case 0:
                    $size = 'xl';
                    break;
                case 1:
                    $size = 'l';
                    break;
                case 2:
                    $size = 'm';
                    break;
                case 3:
                    $size = 's';
                    break;
                default:
                    $size = 'xl';
            }

            $avatarUrl = $avatarUrls[$size];

            $query = $this->database->prepare(
                'SELECT md5_hash, extension FROM psn100_avatars WHERE avatar_url = :avatar_url'
            );
            $query->bindValue(':avatar_url', $avatarUrl, PDO::PARAM_STR);
            $query->execute();
            $result = $query->fetch();

            if (!$result) {
                $avatarContents = @file_get_contents($avatarUrl);
                if ($avatarContents === false) {
                    continue;
                }

                $newPHash = $this->imageHashCalculator->calculatePHash($avatarContents);
                if ($newPHash === null) {
                    continue;
                }

                $query = $this->database->prepare('SELECT DISTINCT md5_hash FROM psn100_avatars');
                $query->execute();
                $existingPHashes = $query->fetchAll(PDO::FETCH_COLUMN);

                foreach ($existingPHashes as $existingPHash) {
                    if ($this->imageHashCalculator->getHammingDistance($newPHash, $existingPHash) <= 10) {
                        $newPHash = $existingPHash;
                        break;
                    }
                }

                $extension = strtolower(pathinfo($avatarUrl, PATHINFO_EXTENSION));
                $avatarFilename = $newPHash . '.' . $extension;
                $avatarPath = $this->avatarStorageDirectory . $avatarFilename;

                if (!file_exists($avatarPath)) {
                    file_put_contents($avatarPath, $avatarContents);
                }

                $query = $this->database->prepare(
                    'INSERT INTO psn100_avatars(size, avatar_url, md5_hash, extension)
                    VALUES(:size, :avatar_url, :md5_hash, :extension)'
                );
                $query->bindValue(':size', $size, PDO::PARAM_STR);
                $query->bindValue(':avatar_url', $avatarUrl, PDO::PARAM_STR);
                $query->bindValue(':md5_hash', $newPHash, PDO::PARAM_STR);
                $query->bindValue(':extension', $extension, PDO::PARAM_STR);
                $query->execute();
            } else {
                $avatarFilename = $result['md5_hash'] . '.' . $result['extension'];
            }

            break;
        }

        return $avatarFilename;
    }

    private function upsertPlayerRecord(object $user, string $country, string $avatarFilename): void
    {
        $plus = (bool) $user->hasPlus();

        $query = $this->database->prepare(
            'INSERT INTO player (
                account_id,
                online_id,
                country,
                avatar_url,
                plus,
                about_me
            )
            VALUES (
                :account_id,
                :online_id,
                :country,
                :avatar_url,
                :plus,
                :about_me
            ) AS new ON DUPLICATE KEY
            UPDATE
                online_id = new.online_id,
                avatar_url = new.avatar_url,
                plus = new.plus,
                about_me = new.about_me'
        );
        $query->bindValue(':account_id', $user->accountId(), PDO::PARAM_INT);
        $query->bindValue(':online_id', $user->onlineId(), PDO::PARAM_STR);
        $query->bindValue(':country', strtolower($country), PDO::PARAM_STR);
        $query->bindValue(':avatar_url', $avatarFilename, PDO::PARAM_STR);
        $query->bindValue(':plus', $plus, PDO::PARAM_BOOL);
        $query->bindValue(':about_me', $user->aboutMe(), PDO::PARAM_STR);
        $query->execute();
    }

    private function lookupPlayerProfile(Client $client, string $onlineId): ?array
    {
        $path = sprintf(
            'https://us-prof.np.community.playstation.net/userProfile/v1/users/%s/profile2',
            rawurlencode($onlineId)
        );

        $query = [
            'fields' => 'accountId,onlineId,currentOnlineId,npId',
        ];

        try {
            $profile = $client->get($path, $query, ['content-type' => 'application/json']);
        } catch (Throwable $exception) {
            if (PsnHttpExceptionClassifier::determineStatusCode($exception) === 404) {
                return null;
            }

            throw $exception;
        }

        $normalized = $this->normalizePlayerProfileResponse($profile);

        return is_array($normalized) ? $normalized : null;
    }

    private function markPlayerAsPrivate(string $onlineId): void
    {
        $query = $this->database->prepare(
            'UPDATE player
            SET `status` = 3, last_updated_date = NOW()
            WHERE online_id = :online_id AND `status` != 1'
        );
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();

        $query = $this->database->prepare('DELETE FROM player_queue WHERE online_id = :online_id');
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();
    }

    private function updateQueuedOnlineId(int $workerId, string $previousOnlineId, string $newOnlineId): void
    {
        $query = $this->database->prepare(
            'UPDATE player_queue SET online_id = :online_id_new WHERE online_id = :online_id_old'
        );
        $query->bindValue(':online_id_new', $newOnlineId, PDO::PARAM_STR);
        $query->bindValue(':online_id_old', $previousOnlineId, PDO::PARAM_STR);
        $query->execute();

        $query = $this->database->prepare(
            'UPDATE setting SET scanning = :scanning, scan_progress = NULL WHERE id = :worker_id'
        );
        $query->bindValue(':scanning', $newOnlineId, PDO::PARAM_STR);
        $query->bindValue(':worker_id', $workerId, PDO::PARAM_INT);
        $query->execute();
    }

    private function fetchStoredCountryByAccountId(int $accountId): ?string
    {
        $query = $this->database->prepare('SELECT country FROM player WHERE account_id = :account_id');
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $country = $query->fetchColumn();

        if (!is_string($country) || $country === '') {
            return null;
        }

        return $country;
    }

    private function findPlayerCountry(Client $client, string $onlineId): ?string
    {
        $normalizedOnlineId = strtolower($onlineId);
        $userCounter = 0;

        try {
            foreach ($client->users()->search($onlineId) as $result) {
                if (strtolower($result->onlineId()) === $normalizedOnlineId) {
                    $country = $result->country();

                    if (!is_string($country) || $country === '') {
                        return null;
                    }

                    $normalizedCountry = strtolower($country);

                    if ($normalizedCountry === 'zz') {
                        return null;
                    }

                    return $normalizedCountry;
                }

                $userCounter++;

                if ($userCounter >= 50) {
                    break;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function updatePlayerCountry(int $accountId, string $country): void
    {
        $query = $this->database->prepare(
            'UPDATE player SET country = :country WHERE account_id = :account_id'
        );
        $query->bindValue(':country', strtolower($country), PDO::PARAM_STR);
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();
    }

    /**
     * @param array<string, mixed> $player
     */
    private function handleInvalidApiResponse(array $player, int $workerId, Throwable $exception): void
    {
        $this->logger->log(
            sprintf(
                'Failed to scan %s because the PlayStation API returned an invalid response: %s',
                (string) ($player['online_id'] ?? ''),
                $exception->getMessage()
            )
        );

        $this->workerScanCoordinator->deferPlayerScanAfterFailure($player, $workerId);
    }

    /**
     * @param array<string, mixed> $player
     */
    private function handlePlayerNotFoundDuringProfileSync(array $player, Exception $exception): void
    {
        $query = $this->database->prepare('DELETE FROM player_queue WHERE online_id = :online_id');
        $query->bindValue(':online_id', $player['online_id'], PDO::PARAM_STR);
        $query->execute();

        if ($exception instanceof NotFoundHttpException) {
            $query = $this->database->prepare('SELECT account_id FROM player WHERE online_id = :online_id');
            $query->bindValue(':online_id', $player['online_id'], PDO::PARAM_STR);
            $query->execute();
            $accountId = $query->fetchColumn();

            if ($accountId) {
                $this->logger->log(
                    sprintf('Sony issues with %s (%s).', $player['online_id'], $accountId)
                );

                $query = $this->database->prepare(
                    'UPDATE player SET `status` = 5, last_updated_date = NOW() WHERE account_id = :account_id'
                );
                $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
                $query->execute();
            }
        }
    }

    private function pauseBeforeRetryingInvalidApiResponse(int $workerId, string $onlineId): void
    {
        $this->workerScanCoordinator->setWaitingScanProgress(
            $workerId,
            sprintf(
                'Encountered an invalid response from the PlayStation API while scanning %s. Waiting 1 minute before retrying.',
                $onlineId
            )
        );

        sleep(60);
    }

    private function normalizePlayerProfileResponse(mixed $profile): array
    {
        if (is_array($profile)) {
            return $profile;
        }

        if (is_object($profile)) {
            try {
                $encoded = json_encode($profile, JSON_THROW_ON_ERROR);
                $decoded = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
                // Fall back to exposing public properties.
            }

            return get_object_vars($profile);
        }

        return [];
    }
}
