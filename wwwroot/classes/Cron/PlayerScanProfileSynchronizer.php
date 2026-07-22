<?php

declare(strict_types=1);

require_once __DIR__ . '/../PsnHttpExceptionClassifier.php';
require_once __DIR__ . '/PlayerAvatarSynchronizer.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/PlayerCountryResolver.php';
require_once __DIR__ . '/PlayerScanPrivacyService.php';
require_once __DIR__ . '/../PlayerRepository.php';
require_once __DIR__ . '/../PlayerStatus.php';

use Tustin\Haste\Exception\NotFoundHttpException;
use Tustin\PlayStation\Client;

/**
 * Resolves PSN profile data and upserts player rows during scans.
 *
 * Encapsulates profile lookup, country resolution, and player persistence that were
 * previously embedded in ThirtyMinuteCronJob.
 */
final class PlayerScanProfileSynchronizer
{
    private const int MAX_INVALID_API_RESPONSE_ATTEMPTS = 2;

    private readonly PlayerAvatarSynchronizer $avatarSynchronizer;
    private readonly PlayerCountryResolver $countryResolver;
    private readonly PlayerScanPrivacyService $privacyService;
    private readonly PlayerRepository $playerRepository;

    public function __construct(
        private readonly PDO $database,
        private readonly Psn100Logger $logger,
        private readonly WorkerScanCoordinator $workerScanCoordinator,
        ?PlayerAvatarSynchronizer $avatarSynchronizer = null,
        ?PlayerCountryResolver $countryResolver = null,
        ?PlayerScanPrivacyService $privacyService = null,
        ?PlayerRepository $playerRepository = null,
    ) {
        $this->avatarSynchronizer = $avatarSynchronizer ?? new PlayerAvatarSynchronizer(
            $database,
            new ImageHashCalculator(),
        );
        $this->countryResolver = $countryResolver ?? new PlayerCountryResolver($database);
        $this->privacyService = $privacyService ?? new PlayerScanPrivacyService(
            $database,
            $workerScanCoordinator,
        );
        $this->playerRepository = $playerRepository ?? new PlayerRepository($database);
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

        $avatarFilename = $this->avatarSynchronizer->synchronizeFromPsnUser($user);
        $this->playerRepository->upsertFromPsnProfile(
            (string) $user->accountId(),
            $user->onlineId(),
            $country,
            $avatarFilename,
            (bool) $user->hasPlus(),
            $user->aboutMe(),
        );

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
            if (!is_finite($accountId) || floor($accountId) !== $accountId) {
                return null;
            }

            $asString = sprintf('%.0f', $accountId);

            return ctype_digit($asString) ? $asString : null;
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
                        $this->privacyService->markAsPrivateByOnlineId($originalOnlineId);

                        return PlayerScanProfileSyncResult::skipPlayer();
                    }

                    $profileAccountId = $profile['accountId'] ?? null;

                    if (!is_string($profileAccountId) || $profileAccountId === '') {
                        $this->privacyService->markAsPrivateByOnlineId($originalOnlineId);

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

                    $country = $this->countryResolver->resolveCountry(
                        $client,
                        $profileAccountId,
                        $user->onlineId(),
                        $this->countryResolver->extractCountryFromNpId($profile['npId'] ?? null),
                    );
                } else {
                    if ($existingAccountId === null) {
                        $this->privacyService->markAsPrivateByOnlineId($originalOnlineId);

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

                    $country = $this->countryResolver->resolveCountry(
                        $client,
                        $existingAccountId,
                        $user->onlineId(),
                    );
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

    /**
     * @param array<string, mixed> $player
     */
    private function handleInvalidApiResponse(array $player, int $workerId, Throwable $exception): void
    {
        // This logging is disabled because it can be very noisy and is not actionable. The worker will automatically defer the player scan for later retry.
        // $this->logger->log(
        //     sprintf(
        //         'Failed to scan %s because the PlayStation API returned an invalid response: %s',
        //         (string) ($player['online_id'] ?? ''),
        //         $exception->getMessage()
        //     )
        // );

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

                $unavailableStatus = PlayerStatus::UNAVAILABLE->value;
                $query = $this->database->prepare(
                    "UPDATE player SET `status` = {$unavailableStatus}, last_updated_date = NOW() WHERE account_id = :account_id"
                );
                $query->bindValue(':account_id', (string) $accountId, PDO::PARAM_STR);
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
