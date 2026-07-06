<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';
require_once __DIR__ . '/../Admin/PsnGameLookupService.php';
require_once __DIR__ . '/../Admin/PlayStationWorkerAuthenticator.php';
require_once __DIR__ . '/../Admin/Worker.php';
require_once __DIR__ . '/../Admin/WorkerService.php';
require_once __DIR__ . '/../AutomaticTrophyTitleMergeService.php';
require_once __DIR__ . '/../ImageHashCalculator.php';
require_once __DIR__ . '/../Psn100Logger.php';
require_once __DIR__ . '/../TrophyHistoryRecorder.php';
require_once __DIR__ . '/../TrophyMergeService.php';
require_once __DIR__ . '/../TrophyMetaRepository.php';
require_once __DIR__ . '/../TrophyImageDirectories.php';
require_once __DIR__ . '/../TrophyImageDownloader.php';
require_once __DIR__ . '/WorkerScanCoordinator.php';
require_once __DIR__ . '/PlayerScanQueueSelector.php';
require_once __DIR__ . '/../TrophyCatalogSynchronizer.php';
require_once __DIR__ . '/../TrophyTitleNameFormatter.php';

use Tustin\Haste\Exception\NotFoundHttpException;
use Tustin\Haste\Exception\UnauthorizedHttpException;
use Tustin\PlayStation\Client;

final class ThirtyMinuteCronJob implements CronJobInterface
{
    private readonly TrophyMetaRepository $trophyMetaRepository;

    private readonly AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService;

    private readonly ImageHashCalculator $imageHashCalculator;
    private readonly PsnGameLookupService $psnGameLookupService;
    private readonly TrophyImageDirectories $imageDirectories;
    private readonly TrophyImageDownloader $imageDownloader;
    private readonly WorkerScanCoordinator $workerScanCoordinator;
    private readonly PlayerScanQueueSelector $playerScanQueueSelector;
    private readonly TrophyTitleNameFormatter $trophyTitleNameFormatter;
    private readonly PlayStationWorkerAuthenticator $workerAuthenticator;
    private readonly TrophyCatalogSynchronizer $trophyCatalogSynchronizer;

    public function __construct(
        private readonly PDO $database,
        private readonly TrophyCalculator $trophyCalculator,
        private readonly Psn100Logger $logger,
        private readonly TrophyHistoryRecorder $historyRecorder,
        private readonly int $workerId,
        ?TrophyMetaRepository $trophyMetaRepository = null,
        ?AutomaticTrophyTitleMergeService $automaticTrophyTitleMergeService = null,
        ?ImageHashCalculator $imageHashCalculator = null,
        ?PsnGameLookupService $psnGameLookupService = null,
        ?TrophyImageDirectories $imageDirectories = null,
        ?TrophyImageDownloader $imageDownloader = null,
        ?WorkerScanCoordinator $workerScanCoordinator = null,
        ?PlayerScanQueueSelector $playerScanQueueSelector = null,
        ?TrophyTitleNameFormatter $trophyTitleNameFormatter = null,
        ?PlayStationWorkerAuthenticator $workerAuthenticator = null,
        ?TrophyCatalogSynchronizer $trophyCatalogSynchronizer = null,
    )
    {
        $this->trophyMetaRepository = $trophyMetaRepository ?? new TrophyMetaRepository($database);
        $this->automaticTrophyTitleMergeService = $automaticTrophyTitleMergeService
            ?? new AutomaticTrophyTitleMergeService($database, new TrophyMergeService($database));
        $this->imageHashCalculator = $imageHashCalculator ?? new ImageHashCalculator();
        $this->psnGameLookupService = $psnGameLookupService ?? PsnGameLookupService::fromDatabase($database);
        $this->imageDirectories = $imageDirectories ?? TrophyImageDirectories::productionDefault();
        $this->imageDownloader = $imageDownloader ?? new TrophyImageDownloader(
            $this->imageHashCalculator,
            function (string $message) use ($logger): void {
                $logger->log($message);
            },
        );
        $this->workerScanCoordinator = $workerScanCoordinator ?? new WorkerScanCoordinator($database);
        $this->playerScanQueueSelector = $playerScanQueueSelector ?? new PlayerScanQueueSelector($database);
        $this->trophyTitleNameFormatter = $trophyTitleNameFormatter ?? new TrophyTitleNameFormatter();
        $this->workerAuthenticator = $workerAuthenticator ?? PlayStationWorkerAuthenticator::fromWorkerService(
            new WorkerService($database),
            null,
            function (int $workerId, string $message) use ($logger): void {
                $logger->log(sprintf('Failed to persist refresh token for worker %d: %s', $workerId, $message));
            },
        );
        $this->trophyCatalogSynchronizer = $trophyCatalogSynchronizer ?? new TrophyCatalogSynchronizer($database);
    }

    /**
     * @param array<int, object> $trophyTitles
     * @param array<string, string> $gameLastUpdatedDate
     */
    private function determineScanStartIndex(array $trophyTitles, array $gameLastUpdatedDate): int
    {
        foreach ($trophyTitles as $index => $trophyTitle) {
            $npid = $trophyTitle->npCommunicationId();

            if (!isset($gameLastUpdatedDate[$npid])) {
                return (int) $index;
            }

            if (!$this->gameTimestampsMatch($trophyTitle->lastUpdatedDateTime(), $gameLastUpdatedDate[$npid])) {
                return (int) $index;
            }
        }

        return count($trophyTitles);
    }

    /**
     * @param list<string> $scannedGames
     */
    private function shouldDeleteMissingZeroPercentGames(int $psnGameCount, int $ourGameCount, array $scannedGames): bool
    {
        if ($psnGameCount <= 0 || $scannedGames === []) {
            return false;
        }

        return $psnGameCount !== $ourGameCount;
    }

    /**
     * @param array<string, mixed> $player
     */
    private function handleInvalidApiResponse(array $player, int $workerId, Throwable $exception): void
    {
        // Middleware/type mismatches from the PSN client are treated as temporary API failures, not permanent "player not found" states.
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
    private function handleInvalidTitleLastUpdatedDateResponse(
        array $player,
        int $workerId,
        string $npCommunicationId
    ): void {
        $this->logger->log(
            sprintf(
                'Failed to scan %s because trophy title %s still has an invalid last updated date after retrying.',
                (string) ($player['online_id'] ?? ''),
                $npCommunicationId
            )
        );

        $this->workerScanCoordinator->deferPlayerScanAfterFailure($player, $workerId);
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

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function retryNotFound(callable $operation, string $description)
    {
        $attempt = 0;
        $delay = 2;
        $maxAttempts = 5;

        while (true) {
            try {
                return $operation();
            } catch (NotFoundHttpException $exception) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    $this->logger->log(sprintf(
                        '%s failed with %s after %d attempts. Aborting retries.',
                        $description,
                        $exception->getMessage(),
                        $attempt
                    ));

                    throw $exception;
                }

                sleep($delay);
                $delay = min($delay * 2, 60);
            }
        }
    }

    private function ensureTrophyTitleIcon(
        object $user,
        object $trophyTitle,
        string $onlineId
    ): ?object {
        $maxAttempts = 2;
        $npCommunicationId = (string) $trophyTitle->npCommunicationId();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $iconUrl = trim((string) $trophyTitle->iconUrl());

            if ($iconUrl !== '') {
                return $trophyTitle;
            }

            if ($attempt === $maxAttempts) {
                $titleName = trim((string) $trophyTitle->name());
                $titleNameForLog = $titleName === '' ? $npCommunicationId : $titleName;

                $this->logger->log(sprintf(
                    'Trophy title "%s" (%s) is missing an icon while processing user %s (attempt %d/%d).',
                    $titleNameForLog,
                    $npCommunicationId,
                    $onlineId,
                    $attempt,
                    $maxAttempts
                ));

                break;
            }

            sleep(2);

            $trophyTitleCollection = $user->trophyTitles();

            foreach ($trophyTitleCollection as $refreshedTitle) {
                if ((string) $refreshedTitle->npCommunicationId() === $npCommunicationId) {
                    $trophyTitle = $refreshedTitle;
                    break;
                }
            }
        }

        return null;
    }

    private function ensureValidTrophyTitleLastUpdatedDate(
        object $user,
        object $trophyTitle,
        string $onlineId
    ): ?object {
        $maxAttempts = 2;
        $npCommunicationId = (string) $trophyTitle->npCommunicationId();

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($this->isValidSonyLastUpdatedDateTime($trophyTitle->lastUpdatedDateTime())) {
                return $trophyTitle;
            }

            if ($attempt === $maxAttempts) {
                $titleName = trim((string) $trophyTitle->name());
                $titleNameForLog = $titleName === '' ? $npCommunicationId : $titleName;

                $this->logger->log(sprintf(
                    'Trophy title "%s" (%s) has an invalid last updated date while processing user %s (attempt %d/%d).',
                    $titleNameForLog,
                    $npCommunicationId,
                    $onlineId,
                    $attempt,
                    $maxAttempts
                ));

                break;
            }

            sleep(2);

            $trophyTitleCollection = $user->trophyTitles();

            foreach ($trophyTitleCollection as $refreshedTitle) {
                if ((string) $refreshedTitle->npCommunicationId() === $npCommunicationId) {
                    $trophyTitle = $refreshedTitle;
                    break;
                }
            }
        }

        return null;
    }

    #[\Override]
    public function run(): void
    {
        $recheck = "";
        $missingGameDeletionCheck = [];
        $missingTrophyTitleRetry = [];
        $trophyTitleCountRetry = [];
        $invalidTitleDateRetry = [];

        while (true) {
            // Login with a token
            $loggedIn = false;
            while (!$loggedIn) {
                $query = $this->database->prepare("SELECT
                    id,
                    refresh_token,
                    npsso,
                    scanning
                FROM
                    setting
                WHERE
                    id = :id");
                $query->bindValue(":id", $this->workerId, PDO::PARAM_INT);
                $query->execute();
                $worker = $query->fetch(PDO::FETCH_ASSOC);

                if ($worker === false) {
                    $message = sprintf(
                        'Worker %d not found in setting table',
                        $this->workerId
                    );
                    $this->logger->log($message);

                    throw new RuntimeException($message);
                }

                try {
                    $workerAccount = new Worker(
                        (int) $worker['id'],
                        (string) ($worker['refresh_token'] ?? ''),
                        (string) ($worker['npsso'] ?? ''),
                        '',
                        new DateTimeImmutable('1970-01-01 00:00:00'),
                        null,
                    );
                    $client = $this->workerAuthenticator->authenticateWorker($workerAccount);
                    $loggedIn = true;
                } catch (TypeError $e) {
                    // Something odd, let's wait a minute
                    $this->workerScanCoordinator->setWaitingScanProgress(
                        (int) $worker['id'],
                        'Encountered a login problem. Waiting 1 minute before retrying.'
                    );
                    sleep(60 * 1);
                } catch (Exception $e) {
                    $this->logger->log("Can't login with worker ". $worker["id"]);

                    // Something went wrong, 'release' the current scanning profile so other workers can pick it up.
                    $this->workerScanCoordinator->releaseWorkerFromCurrentScan((int) $worker['id']);

                    // Wait 30 minutes to not hammer login
                    sleep(60 * 30);
                }
            }

            try {
                $player = $this->playerScanQueueSelector->selectNextCandidate((int) $worker['id']);
                $player = $this->workerScanCoordinator->reservePlayerForScanning((int) $worker['id'], $player);
            } catch (Exception $e) {
                // Probably just an exception for "Integrity constraint violation: 1062 Duplicate entry 'online_id' for key 'setting.scanning'" because another thread was faster then this one
                // Continue and try again!
                continue;
            }

            if ($player === null) {
                continue;
            }

            if ($recheck == $player["online_id"]) {
                $recheck = "";
            } else {
                $recheck = $player["online_id"];
            }

            $onlineId = (string) $player['online_id'];

            $this->workerScanCoordinator->setWaitingScanProgress(
                (int) $worker['id'],
                sprintf('Updating profile data for %s.', $onlineId)
            );

            $maxInvalidApiResponseAttempts = 2;

            // Initialize the current player
            for ($attempt = 1; $attempt <= $maxInvalidApiResponseAttempts; $attempt++) {
                try {
                    $originalOnlineId = (string) $player['online_id'];
                    $existingAccountId = $this->normalizeAccountIdValue($player['account_id'] ?? null);
                    $profileLookup = $this->lookupPlayerProfile($client, $originalOnlineId);
                    $country = 'zz';

                    if ($profileLookup !== null) {
                        $profile = $profileLookup['profile'] ?? null;

                        if (!is_array($profile)) {
                            $this->markPlayerAsPrivate($originalOnlineId);
                            continue 2;
                        }

                        $profileAccountId = $profile['accountId'] ?? null;

                        if (!is_string($profileAccountId) || $profileAccountId === '') {
                            $this->markPlayerAsPrivate($originalOnlineId);
                            continue 2;
                        }

                        $resolvedOnlineId = $this->determineResolvedOnlineId($profile, $originalOnlineId);

                        if ($resolvedOnlineId !== '' && strcasecmp($resolvedOnlineId, $originalOnlineId) !== 0) {
                            $this->updateQueuedOnlineId((int) $worker['id'], $originalOnlineId, $resolvedOnlineId);
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
                            continue 2;
                        }

                        $player['account_id'] = $existingAccountId;
                        $user = $client->users()->find($existingAccountId);

                        $resolvedOnlineId = (string) $user->onlineId();

                        if ($resolvedOnlineId !== '' && strcasecmp($resolvedOnlineId, $originalOnlineId) !== 0) {
                            $this->updateQueuedOnlineId((int) $worker['id'], $originalOnlineId, $resolvedOnlineId);
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

                    // To test for exception (and apparently collects/updates to new onlineId if changed).
                    $user->aboutMe();

                    if (strcasecmp($player['online_id'], $user->onlineId()) !== 0) {
                        $this->updateQueuedOnlineId((int) $worker['id'], (string) $player['online_id'], $user->onlineId());
                        $player['online_id'] = $user->onlineId();
                    }

                    break;
                } catch (TypeError $exception) {
                    if ($attempt < $maxInvalidApiResponseAttempts) {
                        $this->pauseBeforeRetryingInvalidApiResponse((int) $worker['id'], $onlineId);

                        continue;
                    }

                    $this->handleInvalidApiResponse($player, (int) $worker['id'], $exception);

                    continue 2;
                } catch (Exception $e) {
                    // $e->getMessage() == "User not found", and another "Resource not found" error
                    $query = $this->database->prepare("DELETE FROM player_queue
                        WHERE  online_id = :online_id ");
                    $query->bindValue(":online_id", $player["online_id"], PDO::PARAM_STR);
                    $query->execute();

                    if (get_class($e) == "Tustin\Haste\Exception\NotFoundHttpException") {
                        $query = $this->database->prepare("SELECT account_id
                            FROM   player
                            WHERE  online_id = :online_id ");
                        $query->bindValue(":online_id", $player["online_id"], PDO::PARAM_STR);
                        $query->execute();
                        $accountId = $query->fetchColumn();

                        if ($accountId) {
                            // Doesn't seem to exist on Sonys end anymore. Set to status = 5 and let an admin delete the player from our system later.
                            $this->logger->log("Sony issues with ". $player["online_id"] ." (". $accountId .").");

                            $query = $this->database->prepare("UPDATE player
                                SET `status` = 5, last_updated_date = NOW()
                                WHERE  account_id = :account_id ");
                            $query->bindValue(":account_id", $accountId, PDO::PARAM_INT);
                            $query->execute();
                        }
                    }

                    continue 2;
                } catch (Throwable $exception) {
                    if ($attempt < $maxInvalidApiResponseAttempts) {
                        $this->pauseBeforeRetryingInvalidApiResponse((int) $worker['id'], $onlineId);

                        continue;
                    }

                    $this->handleInvalidApiResponse($player, (int) $worker['id'], $exception);

                    continue 2;
                }
            }
            $this->workerScanCoordinator->setWaitingScanProgress(
                (int) $worker['id'],
                sprintf('Updating avatar for %s.', $onlineId)
            );

            // Get the avatar url we want to save
            $avatarUrls = $user->avatarUrls();
            for ($i = 0; $i < 4; $i++) {
                switch ($i) {
                    case 0:
                        $size = "xl";
                        break;
                    case 1:
                        $size = "l";
                        break;
                    case 2:
                        $size = "m";
                        break;
                    case 3:
                        $size = "s";
                        break;
                }
                $avatarUrl = $avatarUrls[$size];

                // Check SQL
                $query = $this->database->prepare("SELECT
                        md5_hash,
                        extension
                    FROM
                        psn100_avatars
                    WHERE
                        avatar_url = :avatar_url");
                $query->bindValue(":avatar_url", $avatarUrl, PDO::PARAM_STR);
                $query->execute();
                $result = $query->fetch();

                if (!$result) { // We doesn't seem to have this avatar
                    $avatarContents = @file_get_contents($avatarUrl);
                    if ($avatarContents === false) {
                        // File not found. Try next.
                        continue;
                    }

                    $newPHash = $this->imageHashCalculator->calculatePHash($avatarContents);
                    if ($newPHash === null) {
                        // Something went wrong with the image processing, skip saving this avatar.
                        continue;
                    }

                    $query = $this->database->prepare("SELECT DISTINCT md5_hash FROM psn100_avatars");
                    $query->execute();
                    $existingPHashes = $query->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($existingPHashes as $existingPHash) {
                        if ($this->imageHashCalculator->getHammingDistance($newPHash, $existingPHash) <= 10) {
                            $newPHash = $existingPHash;
                            break; 
                        }
                    }

                    $extension = strtolower(pathinfo($avatarUrl, PATHINFO_EXTENSION));

                    $avatarFilename = $newPHash .".". $extension;
                    if (!file_exists("/home/psn100/public_html/img/avatar/". $avatarFilename)) {
                        file_put_contents("/home/psn100/public_html/img/avatar/". $avatarFilename, $avatarContents);
                    }

                    // SQL Insert
                    $query = $this->database->prepare("INSERT INTO psn100_avatars(
                            size,
                            avatar_url,
                            md5_hash,
                            extension
                        )
                        VALUES(
                            :size,
                            :avatar_url,
                            :md5_hash,
                            :extension
                        )");
                    $query->bindValue(":size", $size, PDO::PARAM_STR);
                    $query->bindValue(":avatar_url", $avatarUrl, PDO::PARAM_STR);
                    $query->bindValue(":md5_hash", $newPHash, PDO::PARAM_STR);
                    $query->bindValue(":extension", $extension, PDO::PARAM_STR);
                    $query->execute();
                } else {
                    $avatarFilename = $result["md5_hash"] .".". $result["extension"];
                }

                // We are done, no need to check other images.
                break;
            }

            // Plus is null or 1, we don't want null so this will make it 0 if so.
            $plus = (bool)$user->hasPlus();

            // Add/update player into database
            $query = $this->database->prepare("INSERT INTO
                    player (
                        account_id,
                        online_id,
                        country,
                        avatar_url,
                        plus,
                        about_me
                    )
                VALUES
                    (
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
                    about_me = new.about_me
            ");
            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
            $query->bindValue(":online_id", $user->onlineId(), PDO::PARAM_STR);
            $query->bindValue(":country", strtolower($country), PDO::PARAM_STR);
            $query->bindValue(":avatar_url", $avatarFilename, PDO::PARAM_STR);
            $query->bindValue(":plus", $plus, PDO::PARAM_BOOL);
            $query->bindValue(":about_me", $user->aboutMe(), PDO::PARAM_STR);
            // Don't insert level/progress/platinum/gold/silver/bronze here since our site recalculate this.
            $query->execute();

            try {
                $level = 0;
                $level = $user->trophySummary();
            } catch (Exception $e) {
                // Wait 5 minutes to not hammer Sony
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Encountered a problem while scanning. Waiting 1 minute before retrying.'
                );
                sleep(60 * 1);

                // Something is odd with PSN, break out and try again later.
                break;
            }

            $privateUser = false;
            try {
                $level = 0;
                $level = $user->trophySummary()->level();
            } catch (TypeError $e) {
                // Rare error, wait 1 minute to not hammer Sony and try again.
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Encountered a problem while scanning. Waiting 1 minute before retrying.'
                );
                sleep(60 * 1);
                break;
            } catch (Exception $e) {
                // Potentially private profile, wait 1 minute and retry before updating the status.
                $this->workerScanCoordinator->setWaitingScanProgress(
                    (int) $worker['id'],
                    'Profile scan failed, waiting 1 minute before confirming privacy.'
                );
                sleep(60 * 1);

                try {
                    $level = 0;
                    $level = $user->trophySummary()->level();
                } catch (TypeError $retryException) {
                    // Rare error, wait 1 minute to not hammer Sony and try again.
                    $this->workerScanCoordinator->setWaitingScanProgress(
                        (int) $worker['id'],
                        'Encountered a problem while scanning. Waiting 1 minute before retrying.'
                    );
                    sleep(60 * 1);
                    break;
                } catch (Exception $retryException) {
                    // Profile seem to be private, set status to 3 to hide all trophies.
                    $query = $this->database->prepare("UPDATE
                            player
                        SET
                            status = 3,
                            last_updated_date = NOW()
                        WHERE
                            account_id = :account_id
                            AND status != 1
                    ");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();

                    // Delete user from the queue
                    $query = $this->database->prepare("DELETE FROM player_queue
                        WHERE  online_id = :online_id ");
                    $query->bindValue(":online_id", $user->onlineId(), PDO::PARAM_STR);
                    $query->execute();

                    $privateUser = true;
                }
            }

            try {
                if (!$privateUser) {
                    $totalTrophiesStart = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();

                    if ($level !== 0) {
                        $query = $this->database->prepare("SELECT np_communication_id,
                                last_updated_date
                            FROM   trophy_title_player
                            WHERE  account_id = :account_id AND np_communication_id LIKE 'N%'");
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                        $query->execute();
                        $gameLastUpdatedDate = $query->fetchAll(PDO::FETCH_KEY_PAIR);

                        $this->workerScanCoordinator->setWaitingScanProgress(
                            (int) $worker['id'],
                            sprintf('Fetching game list for %s.', $onlineId)
                        );

                        $trophyTitleFetchCompleted = false;
                        try {
                            $trophyTitleCollection = $user->trophyTitles();
                            $trophyTitles = iterator_to_array($trophyTitleCollection->getIterator());
                        } catch (TypeError $exception) {
                            // Unable to fetch trophy titles for player['online_id'] due to unexpected response.
                            sleep(5);

                            continue;
                        }

                        $trophyTitleFetchCompleted = true;
                        $psnGameCount = count($trophyTitles);
                        $localGameCount = count($gameLastUpdatedDate);
                        $gameCountDelta = $psnGameCount - $localGameCount;

                        if ($gameCountDelta <= -50) {
                            if (!($trophyTitleCountRetry[$onlineId] ?? false)) {
                                $trophyTitleCountRetry[$onlineId] = true;

                                $this->workerScanCoordinator->setWaitingScanProgress(
                                    (int) $worker['id'],
                                    'Trophy title count lower than expected. Waiting 1 minute before retrying.'
                                );

                                sleep(60 * 1);
                                $recheck = '';

                                continue;
                            }
                        }
                        usort(
                            $trophyTitles,
                            function ($left, $right): int {
                                $leftTimestamp = strtotime($left->lastUpdatedDateTime());
                                $rightTimestamp = strtotime($right->lastUpdatedDateTime());

                                if ($leftTimestamp === false || $rightTimestamp === false) {
                                    return strcmp($left->lastUpdatedDateTime(), $right->lastUpdatedDateTime());
                                }

                                return $leftTimestamp <=> $rightTimestamp;
                            }
                        );

                        $scanStartIndex = $this->determineScanStartIndex($trophyTitles, $gameLastUpdatedDate);
                        $totalGamesToProcess = max(0, count($trophyTitles) - $scanStartIndex);
                        $currentScanPosition = 0;
                        $scannedGames = array();
                        $restartScan = false;

                        // Look through each and every game
                        foreach ($trophyTitles as $index => $trophyTitle) {
                            $npid = $trophyTitle->npCommunicationId();
                            array_push($scannedGames, $npid);

                            if ($index < $scanStartIndex) {
                                continue;
                            }

                            $trophyTitle = $this->ensureTrophyTitleIcon(
                                $user,
                                $trophyTitle,
                                (string) $player['online_id']
                            );

                            if ($trophyTitle === null) {
                                $this->logger->log(sprintf(
                                    'Unable to fetch trophy title icon for %s. Restarting scan.',
                                    (string) $player['online_id']
                                ));
                                $restartScan = true;

                                break;
                            }

                            $trophyTitle = $this->ensureValidTrophyTitleLastUpdatedDate(
                                $user,
                                $trophyTitle,
                                (string) $player['online_id']
                            );

                            if ($trophyTitle === null) {
                                if ($this->shouldRetryInvalidTitleLastUpdatedDate($invalidTitleDateRetry, $onlineId, $npid)) {
                                    $this->markInvalidTitleLastUpdatedDateRetried($invalidTitleDateRetry, $onlineId, $npid);

                                    $this->logger->log(sprintf(
                                        'Unable to fetch a valid last updated date for %s on title %s. Waiting 1 minute before retrying.',
                                        (string) $player['online_id'],
                                        $npid
                                    ));
                                    $this->pauseBeforeRetryingInvalidApiResponse((int) $worker['id'], $onlineId);
                                    $restartScan = true;

                                    break;
                                }

                                $this->handleInvalidTitleLastUpdatedDateResponse(
                                    $player,
                                    (int) $worker['id'],
                                    $npid
                                );

                                continue 2;
                            }

                            if ($totalGamesToProcess > 0) {
                                $currentScanPosition++;
                                $this->workerScanCoordinator->setWorkerScanProgress(
                                    (int) $worker['id'],
                                    [
                                        'current' => $currentScanPosition,
                                        'total' => $totalGamesToProcess,
                                        'title' => $trophyTitle->name(),
                                        'npCommunicationId' => $npid,
                                    ]
                                );
                            }

                            $newTrophies = false;
                            $titleDataChanged = false;
                            $groupDataChanged = false;
                            $trophyDataChanged = false;

                            // Does this user already have the game?
                            if (
                                isset($gameLastUpdatedDate[$npid])
                                && $this->gameTimestampsMatch(
                                    $trophyTitle->lastUpdatedDateTime(),
                                    $gameLastUpdatedDate[$npid]
                                )
                            ) {
                                // Game seems scanned already, skip to next.
                                continue;
                            }

                            // Add trophy title (game) information into database
                            $titleId = null;

                            $platforms = "";
                            foreach ($trophyTitle->platform() as $platform) {
                                $platformValue = $platform->value;
                                if ($platformValue === 'PSPC') {
                                    $platformValue = 'PC';
                                }

                                $platforms .= $platformValue .",";
                            }
                            $platforms = rtrim($platforms, ",");

                            $sanitizedTitleName = $this->trophyTitleNameFormatter->format($trophyTitle->name());

                            $existingTitle = $this->trophyCatalogSynchronizer->fetchExistingTrophyTitleRow($npid);
                            $isNewTitle = $existingTitle === null;
                            $incomingSetVersion = $trophyTitle->trophySetVersion();
                            $setVersionForUpdate = $this->resolveSetVersionForUpdate(
                                $incomingSetVersion,
                                is_array($existingTitle) ? ($existingTitle['set_version'] ?? null) : null
                            );
                            $incomingVersionIsOlderThanStored = $this->isIncomingSetVersionOlderThanStored(
                                $incomingSetVersion,
                                is_array($existingTitle) ? ($existingTitle['set_version'] ?? null) : null
                            );

                            $previousTitleIconFilename = $existingTitle['icon_url'] ?? null;
                            $titleIconFilename = $previousTitleIconFilename;
                            $titleIconMissing = $titleIconFilename === null
                                || !file_exists($this->imageDirectories->title . $titleIconFilename);

                            $titleNeedsUpdate = $existingTitle === null
                                || (
                                    !$incomingVersionIsOlderThanStored
                                    && (
                                        $existingTitle['detail'] !== $trophyTitle->detail()
                                        || $existingTitle['set_version'] !== $setVersionForUpdate
                                    )
                                );

                            if ($existingTitle === null || $titleNeedsUpdate || $titleIconMissing) {
                                $titleIconFilename = $this->imageDownloader->downloadMandatoryForScan(
                                    $trophyTitle->iconUrl(),
                                    $this->imageDirectories->title,
                                    sprintf('title icon for "%s" (%s)', $trophyTitle->name(), $npid),
                                    $previousTitleIconFilename
                                );
                            }

                            if ($existingTitle === null || $titleNeedsUpdate || $titleIconMissing) {
                                $query = $this->database->prepare("INSERT INTO trophy_title(
                                        np_communication_id,
                                        name,
                                        detail,
                                        icon_url,
                                        platform,
                                        set_version
                                    )
                                    VALUES(
                                        :np_communication_id,
                                        :name,
                                        :detail,
                                        :icon_url,
                                        :platform,
                                        :set_version
                                    ) AS new
                                    ON DUPLICATE KEY
                                    UPDATE
                                        detail = CASE
                                            WHEN :incoming_version_is_older = 1 THEN trophy_title.detail
                                            ELSE new.detail
                                        END,
                                        icon_url = new.icon_url,
                                        set_version = CASE
                                            WHEN trophy_title.set_version IS NULL OR TRIM(trophy_title.set_version) = '' THEN new.set_version
                                            WHEN CAST(SUBSTRING_INDEX(TRIM(new.set_version), '.', 1) AS UNSIGNED)
                                                > CAST(SUBSTRING_INDEX(TRIM(trophy_title.set_version), '.', 1) AS UNSIGNED) THEN new.set_version
                                            WHEN CAST(SUBSTRING_INDEX(TRIM(new.set_version), '.', 1) AS UNSIGNED)
                                                = CAST(SUBSTRING_INDEX(TRIM(trophy_title.set_version), '.', 1) AS UNSIGNED)
                                                AND CAST(SUBSTRING_INDEX(TRIM(new.set_version), '.', -1) AS UNSIGNED)
                                                    >= CAST(SUBSTRING_INDEX(TRIM(trophy_title.set_version), '.', -1) AS UNSIGNED) THEN new.set_version
                                            ELSE trophy_title.set_version
                                        END");
                                $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                                $query->bindValue(":name", $sanitizedTitleName, PDO::PARAM_STR);
                                $query->bindValue(":detail", $trophyTitle->detail(), PDO::PARAM_STR);
                                $query->bindValue(":icon_url", $titleIconFilename, PDO::PARAM_STR);
                                $query->bindValue(":platform", $platforms, PDO::PARAM_STR);
                                $query->bindValue(":set_version", $setVersionForUpdate, PDO::PARAM_STR);
                                $query->bindValue(":incoming_version_is_older", $incomingVersionIsOlderThanStored ? 1 : 0, PDO::PARAM_INT);
                                // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                                $query->execute();

                                if ($query->rowCount() > 0) {
                                    $titleDataChanged = true;
                                }
                            }

                            $metaQuery = $this->database->prepare("INSERT IGNORE INTO trophy_title_meta (
                                    np_communication_id,
                                    message
                                )
                                VALUES (
                                    :np_communication_id,
                                    :message
                                )");
                            $metaQuery->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                            $metaQuery->bindValue(":message", '', PDO::PARAM_STR);
                            $metaQuery->execute();

                            // Get "groups" (game and DLCs)
                            try {
                                $trophyData = $this->psnGameLookupService->fetchTrophyDataForNpCommunicationId($npid, $client);
                            } catch (Throwable $exception) {
                                $this->logger->log(sprintf(
                                    'Unable to fetch trophy data for %s (%s): %s',
                                    $trophyTitle->name(),
                                    $npid,
                                    $exception->getMessage()
                                ));
                                $restartScan = true;

                                break;
                            }

                            $trophyGroups = $trophyData['trophyGroups'] ?? [];
                            if (!is_array($trophyGroups)) {
                                $trophyGroups = [];
                            }

                            $topLevelTrophies = $trophyData['trophies'] ?? [];
                            if (!is_array($topLevelTrophies)) {
                                $topLevelTrophies = [];
                            }

                            $fallbackGroupTrophies = [];
                            foreach ($topLevelTrophies as $topLevelTrophy) {
                                if (!is_array($topLevelTrophy)) {
                                    continue;
                                }

                                $topLevelTrophyGroupId = (string) ($topLevelTrophy['trophyGroupId'] ?? '');
                                if ($topLevelTrophyGroupId === '') {
                                    continue;
                                }

                                $fallbackGroupTrophies[$topLevelTrophyGroupId][] = $topLevelTrophy;
                            }

                            foreach ($trophyGroups as $trophyGroup) {
                                if (!is_array($trophyGroup)) {
                                    continue;
                                }

                                $trophyGroupId = (string) ($trophyGroup['trophyGroupId'] ?? '');
                                if ($trophyGroupId === '') {
                                    continue;
                                }

                                $trophyGroupName = (string) ($trophyGroup['trophyGroupName'] ?? '');
                                $trophyGroupDetail = (string) ($trophyGroup['trophyGroupDetail'] ?? '');
                                $trophyGroupIconUrl = (string) ($trophyGroup['trophyGroupIconUrl'] ?? '');
                                $groupNewTrophies = false;
                                // Add trophy group (game + dlcs) into database
                                $existingGroup = $this->trophyCatalogSynchronizer->fetchExistingTrophyGroup($npid, $trophyGroupId);

                                $previousGroupIconFilename = $existingGroup['icon_url'] ?? null;
                                $groupIconFilename = $previousGroupIconFilename;
                                $groupIconMissing = $groupIconFilename === null
                                    || !file_exists($this->imageDirectories->group . $groupIconFilename);

                                $groupNeedsUpdate = $existingGroup === null
                                    || $existingGroup['name'] !== $trophyGroupName
                                    || $existingGroup['detail'] !== $trophyGroupDetail;

                                if ($existingGroup === null || $groupNeedsUpdate || $groupIconMissing || $titleNeedsUpdate) {
                                    $groupIconFilename = $this->imageDownloader->downloadMandatoryForScan(
                                        $trophyGroupIconUrl,
                                        $this->imageDirectories->group,
                                        sprintf('trophy group icon for "%s" (%s/%s)', $trophyGroupName, $npid, $trophyGroupId),
                                        $previousGroupIconFilename
                                    );
                                }

                                if ($existingGroup === null || $groupNeedsUpdate || $groupIconMissing || $titleNeedsUpdate) {
                                    $groupAffectedRows = $this->trophyCatalogSynchronizer->upsertTrophyGroup(
                                        $npid,
                                        $trophyGroupId,
                                        $trophyGroupName,
                                        $trophyGroupDetail,
                                        $groupIconFilename,
                                    );

                                    if ($groupAffectedRows > 0) {
                                        $groupDataChanged = true;
                                    }
                                }

                                $groupTrophies = $trophyGroup['trophies'] ?? [];
                                if (!is_array($groupTrophies)) {
                                    $groupTrophies = [];
                                }

                                if ($groupTrophies === [] && isset($fallbackGroupTrophies[$trophyGroupId])) {
                                    $groupTrophies = $fallbackGroupTrophies[$trophyGroupId];
                                }

                                if ($groupTrophies === []) {
                                    $this->logger->log(sprintf(
                                        'Unable to sync trophies for %s (%s/%s): trophy group payload did not include any trophies.',
                                        $trophyTitle->name(),
                                        $npid,
                                        $trophyGroupId
                                    ));
                                    $restartScan = true;

                                    break;
                                }

                                // Add trophies into database
                                foreach ($groupTrophies as $trophy) {
                                    if (!is_array($trophy)) {
                                        continue;
                                    }

                                    $rawTrophyOrderId = $trophy['trophyId'] ?? null;
                                    if (!is_numeric($rawTrophyOrderId)) {
                                        continue;
                                    }

                                    $trophyOrderId = (int) $rawTrophyOrderId;
                                    if ($trophyOrderId < 0) {
                                        continue;
                                    }

                                    $existingTrophy = $this->trophyCatalogSynchronizer->fetchExistingTrophy(
                                        $npid,
                                        $trophyGroupId,
                                        $trophyOrderId,
                                    );

                                    $trophyHidden = (int) ($trophy['trophyHidden'] ?? 0);

                                    $rawProgressTargetValue = $trophy['trophyProgressTargetValue'] ?? null;

                                    $existingProgressTargetValue = null;
                                    $existingRewardName = null;
                                    $existingRewardImageFilename = null;
                                    $existingIconFilename = null;

                                    if ($existingTrophy !== null) {
                                        $existingProgressTargetValue = $existingTrophy['progress_target_value'] === null
                                            ? null
                                            : (int) $existingTrophy['progress_target_value'];
                                        $existingRewardName = $existingTrophy['reward_name'];
                                        $existingRewardImageFilename = $existingTrophy['reward_image_url'];
                                        $existingIconFilename = $existingTrophy['icon_url'];
                                    }

                                    $progressTargetValue = $rawProgressTargetValue === null || $rawProgressTargetValue === ''
                                        ? null
                                        : (int) $rawProgressTargetValue;

                                    $rewardName = (string) ($trophy['trophyRewardName'] ?? '');
                                    $rewardName = $rewardName === '' ? null : $rewardName;

                                    $rewardImageUrl = $trophy['trophyRewardImageUrl'] ?? null;
                                    $rewardImageShouldBeNull = $rewardImageUrl === null || $rewardImageUrl === '';

                                    $trophyTypeEnumValue = strtolower((string) ($trophy['trophyType'] ?? ''));
                                    if ($trophyTypeEnumValue === '') {
                                        $trophyTypeEnumValue = 'bronze';
                                    }

                                    $trophyName = (string) ($trophy['trophyName'] ?? '');
                                    $trophyDetail = (string) ($trophy['trophyDetail'] ?? '');
                                    $trophyIconUrl = (string) ($trophy['trophyIconUrl'] ?? '');

                                    $trophyNeedsUpdate = $existingTrophy === null
                                        || (int) ($existingTrophy['hidden'] ?? -1) !== $trophyHidden
                                        || ($existingTrophy['type'] ?? '') !== $trophyTypeEnumValue
                                        || ($existingTrophy['name'] ?? '') !== $trophyName
                                        || ($existingTrophy['detail'] ?? '') !== $trophyDetail
                                        || $existingProgressTargetValue !== $progressTargetValue
                                        || ($existingRewardName !== $rewardName)
                                        || ($rewardImageShouldBeNull && $existingRewardImageFilename !== null)
                                        || (!$rewardImageShouldBeNull && $existingRewardImageFilename === null);

                                    $iconMissing = $existingIconFilename === null
                                        || !file_exists($this->imageDirectories->trophy . $existingIconFilename);

                                    $previousIconFilename = $existingIconFilename;

                                    if ($existingTrophy === null || $trophyNeedsUpdate || $iconMissing || $groupNeedsUpdate || $titleNeedsUpdate) {
                                        $trophyIconFilename = $this->imageDownloader->downloadMandatoryForScan(
                                            $trophyIconUrl,
                                            $this->imageDirectories->trophy,
                                            sprintf(
                                                'trophy icon for "%s" (%s/%s/%d)',
                                                $trophyName,
                                                $npid,
                                                $trophyGroupId,
                                                $trophyOrderId
                                            ),
                                            $previousIconFilename
                                        );
                                    } else {
                                        $trophyIconFilename = $existingIconFilename;
                                    }

                                    $rewardImageMissing = false;

                                    if ($rewardImageShouldBeNull) {
                                        $rewardImageFilename = null;
                                    } else {
                                        $rewardImageMissing = $existingRewardImageFilename === null
                                            || !file_exists($this->imageDirectories->reward . $existingRewardImageFilename);

                                        if ($existingTrophy === null || $trophyNeedsUpdate || $rewardImageMissing || $groupNeedsUpdate || $titleNeedsUpdate) {
                                            $rewardImageFilename = $this->imageDownloader->downloadOptionalForScan(
                                                $rewardImageUrl === null ? '' : (string) $rewardImageUrl,
                                                $this->imageDirectories->reward,
                                                sprintf(
                                                    'reward image for "%s" (%s/%s/%d)',
                                                    $trophyName,
                                                    $npid,
                                                    $trophyGroupId,
                                                    $trophyOrderId
                                                ),
                                                $existingRewardImageFilename
                                            );
                                        } else {
                                            $rewardImageFilename = $existingRewardImageFilename;
                                        }
                                    }

                                    $shouldUpsertTrophy = $existingTrophy === null
                                        || $trophyNeedsUpdate
                                        || $iconMissing
                                        || (!$rewardImageShouldBeNull && $rewardImageMissing);

                                    $trophyAffectedRows = 0;

                                    if ($shouldUpsertTrophy) {
                                        $trophyAffectedRows = $this->trophyCatalogSynchronizer->upsertTrophy(
                                            $npid,
                                            $trophyGroupId,
                                            $trophyOrderId,
                                            $trophyHidden,
                                            $trophyTypeEnumValue,
                                            $trophyName,
                                            $trophyDetail,
                                            $trophyIconFilename,
                                            $progressTargetValue,
                                            $rewardName,
                                            $rewardImageFilename,
                                        );

                                        if ($trophyAffectedRows > 0) {
                                            $trophyDataChanged = true;
                                        }

                                        if ($trophyAffectedRows === 1) {
                                            $newTrophies = true;
                                            $groupNewTrophies = true;
                                        }
                                    }

                                    $this->ensureTrophyMetaRow(
                                        $npid,
                                        $trophyGroupId,
                                        $trophyOrderId
                                    );
                                }

                                if ($groupNewTrophies) {
                                    $query = $this->database->prepare("SELECT status
                                        FROM   trophy_title_meta
                                        WHERE  np_communication_id = :np_communication_id ");
                                    $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                                    $query->execute();
                                    $status = $query->fetchColumn();
                                    if ($status == 2) { // A "Merge Title" have gotten new trophies. Add a log about it so admin can check it out later and fix this.
                                        $this->logger->log("New trophies added for ". $trophyTitle->name() .". ". $npid . ", ". $trophyGroupId .", ". $trophyGroupName);
                                    } else {
                                        $this->logger->log("SET VERSION for ". $trophyTitle->name() .". ". $npid . ", ". $trophyGroupId .", ". $trophyGroupName);
                                    }
                                }
                            }

                            if ($restartScan) {
                                break;
                            }

                            if ($titleDataChanged || $groupDataChanged || $trophyDataChanged) {
                                if ($titleId === null) {
                                    $titleId = $this->findTrophyTitleId($npid);
                                }

                                if ($titleId !== null) {
                                    $this->historyRecorder->recordByTitleId($titleId);
                                }
                            }

                            $mergeParentsToRecompute = [];
                            if ($isNewTitle) {
                                $mergeParentsToRecompute = $this->automaticTrophyTitleMergeService->handleNewTitle($npid);
                            }

                            if ($newTrophies) {
                                if ($titleId === null) {
                                    $titleId = $this->findTrophyTitleId($npid);
                                }

                                if ($titleId === null) {
                                    continue;
                                }

                                $query = $this->database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_VERSION', :param_1)");
                                $query->bindValue(":param_1", $titleId, PDO::PARAM_INT);
                                $query->execute();
                            }

                            // Fetch user trophies
                            $trophyGroups = $this->retryNotFound(
                                fn () => $trophyTitle->trophyGroups(),
                                sprintf('Fetching trophy groups for %s (%s)', $trophyTitle->name(), $npid)
                            );

                            foreach ($trophyGroups as $trophyGroup) {
                                $groupTrophies = $this->retryNotFound(
                                    fn () => $trophyGroup->trophies(),
                                    sprintf(
                                        'Fetching trophies for %s (%s/%s)',
                                        $trophyTitle->name(),
                                        $npid,
                                        $trophyGroup->id()
                                    )
                                );

                                foreach ($groupTrophies as $trophy) {
                                    $trophyEarned = $trophy->earned();
                                    $progress = (clone $trophy)->progress();
                                    if ($trophyEarned || ($progress != '' && intval($progress) > 0)) {
                                        if ($trophy->earnedDateTime() === '') {
                                            $dtAsTextForInsert = null;
                                        } else {
                                            $dtAsTextForInsert = $this->formatDateTimeForDatabase($trophy->earnedDateTime());
                                        }

                                        $query = $this->database->prepare("INSERT INTO trophy_earned(
                                                np_communication_id,
                                                group_id,
                                                order_id,
                                                account_id,
                                                earned_date,
                                                progress,
                                                earned
                                            )
                                            VALUES(
                                                :np_communication_id,
                                                :group_id,
                                                :order_id,
                                                :account_id,
                                                :earned_date,
                                                :progress,
                                                :earned
                                            ) AS new
                                            ON DUPLICATE KEY
                                            UPDATE
                                                earned_date = IF(trophy_earned.earned = 0, new.earned_date, trophy_earned.earned_date),
                                                progress = new.progress,
                                                earned = new.earned");
                                        $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                                        $query->bindValue(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                                        $query->bindValue(":order_id", $trophy->id(), PDO::PARAM_INT);
                                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                        $query->bindValue(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                                        if ($progress === '') {
                                            $progress = null;
                                        } else {
                                            $progress = intval($progress);
                                        }
                                        $query->bindValue(":progress", $progress, PDO::PARAM_INT);
                                        $query->bindValue(":earned", $trophyEarned, PDO::PARAM_INT);
                                        $query->execute();

                                        // Check if "merge"-trophy
                                        $query = $this->database->prepare("SELECT parent_np_communication_id,
                                                    parent_group_id,
                                                    parent_order_id
                                            FROM   trophy_merge
                                            WHERE  child_np_communication_id = :child_np_communication_id
                                                    AND child_group_id = :child_group_id
                                                    AND child_order_id = :child_order_id ");
                                        $query->bindValue(":child_np_communication_id", $npid, PDO::PARAM_STR);
                                        $query->bindValue(":child_group_id", $trophyGroup->id(), PDO::PARAM_STR);
                                        $query->bindValue(":child_order_id", $trophy->id(), PDO::PARAM_INT);
                                        $query->execute();
                                        $parent = $query->fetch();
                                        if ($parent !== false) {
                                            $query = $this->database->prepare("INSERT INTO trophy_earned(
                                                    np_communication_id,
                                                    group_id,
                                                    order_id,
                                                    account_id,
                                                    earned_date,
                                                    progress,
                                                    earned
                                                )
                                                VALUES(
                                                    :np_communication_id,
                                                    :group_id,
                                                    :order_id,
                                                    :account_id,
                                                    :earned_date,
                                                    :progress,
                                                    :earned
                                                ) AS new
                                                ON DUPLICATE KEY
                                                UPDATE
                                                    earned_date = IF(trophy_earned.earned_date < new.earned_date, trophy_earned.earned_date, new.earned_date),
                                                    progress = IF(trophy_earned.progress IS NULL, new.progress,
                                                        IF(new.progress IS NULL, trophy_earned.progress,
                                                            IF(trophy_earned.progress > new.progress, trophy_earned.progress, new.progress)
                                                        )
                                                    ),
                                                    earned = IF(trophy_earned.earned = 1, trophy_earned.earned, new.earned)");
                                            $query->bindValue(":np_communication_id", $parent["parent_np_communication_id"], PDO::PARAM_STR);
                                            $query->bindValue(":group_id", $parent["parent_group_id"], PDO::PARAM_STR);
                                            $query->bindValue(":order_id", $parent["parent_order_id"], PDO::PARAM_INT);
                                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                            $query->bindValue(":earned_date", $dtAsTextForInsert, PDO::PARAM_STR);
                                            $query->bindValue(":progress", $progress, PDO::PARAM_INT);
                                            $query->bindValue(":earned", $trophyEarned, PDO::PARAM_INT);
                                            $query->execute();
                                        }
                                    }
                                }

                                // Recalculate trophies for trophy group and player
                                $this->trophyCalculator->recalculateTrophyGroup($npid, $trophyGroup->id(), (int) $user->accountId());
                            }

                            // Recalculate trophies for trophy title and player
                            $this->trophyCalculator->recalculateTrophyTitle($npid, $trophyTitle->lastUpdatedDateTime(), $newTrophies, (int) $user->accountId(), false);

                            // Game Merge stuff
                            $query = $this->database->prepare("SELECT DISTINCT parent_np_communication_id, 
                                                parent_group_id 
                                FROM   trophy_merge 
                                WHERE  child_np_communication_id = :child_np_communication_id ");
                            $query->bindValue(":child_np_communication_id", $npid, PDO::PARAM_STR);
                            $query->execute();
                            while ($row = $query->fetch()) {
                                $this->trophyCalculator->recalculateTrophyGroup($row["parent_np_communication_id"], $row["parent_group_id"], (int) $user->accountId());
                                $this->trophyCalculator->recalculateTrophyTitle($row["parent_np_communication_id"], $trophyTitle->lastUpdatedDateTime(), false, (int) $user->accountId(), true);
                            }

                            foreach ($mergeParentsToRecompute as $mergeParent) {
                                $this->automaticTrophyTitleMergeService->recomputeMergeProgressByParent($mergeParent);
                            }
                        }

                        if ($restartScan) {
                            $recheck = '';

                            continue;
                        }

                        $totalTrophiesEnd = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();
                        if ($totalTrophiesStart != $totalTrophiesEnd) { // New trophies during the scan, restart and get them as well.
                            $recheck = "";
                            continue;
                        }

                        // Delete missing 0% games (and this will also delete hidden games, and any trophies for those hidden games)
                        $query = $this->database->prepare("SELECT COUNT(ttp.np_communication_id)
                            FROM   trophy_title_player ttp
                            WHERE  ttp.account_id = :account_id AND ttp.np_communication_id LIKE 'N%'");
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                        $query->execute();
                        $ourGameCount = $query->fetchColumn();

                        $scanReachedEnd = $currentScanPosition === $totalGamesToProcess;
                        $scanCompletedCleanly = $trophyTitleFetchCompleted
                            && $scanReachedEnd
                            && !$restartScan
                            && $recheck === '';

                        $shouldDeleteMissingGames = $this->shouldDeleteMissingZeroPercentGames(
                            (int) $psnGameCount,
                            (int) $ourGameCount,
                            $scannedGames
                        );

                        $gameCountDelta = $psnGameCount - $ourGameCount;

                        if (
                            $shouldDeleteMissingGames
                            && $gameCountDelta <= -50
                            && !$scanCompletedCleanly
                        ) {
                            // $this->logger->log(sprintf(
                            //     'Skipping deletion for %s (%d) because the scan did not complete cleanly (psn=%d, local=%d).',
                            //     (string) $player['online_id'],
                            //     (int) $user->accountId(),
                            //     (int) $psnGameCount,
                            //     (int) $ourGameCount
                            // ));
                            $shouldDeleteMissingGames = false;
                        }

                        if ($shouldDeleteMissingGames) {
                            if (!($missingGameDeletionCheck[$onlineId] ?? false)) {
                                $missingGameDeletionCheck[$onlineId] = true;
                                $this->workerScanCoordinator->setWaitingScanProgress(
                                    (int) $worker['id'],
                                    'Waiting 5 minutes before retrying because of game deletion check.'
                                );
                                sleep(60 * 5);
                                $recheck = '';

                                continue;
                            }

                            $query = $this->database->prepare("SELECT ttp.np_communication_id
                                FROM   trophy_title_player ttp
                                WHERE  ttp.account_id = :account_id AND ttp.np_communication_id LIKE 'N%'");
                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                            $query->execute();
                            $playerGames = $query->fetchAll();

                            foreach ($playerGames as $playerGame) {
                                $game = $playerGame["np_communication_id"];
                                if (!in_array($game, $scannedGames)) {
                                    $query = $this->database->prepare("SELECT ttm.parent_np_communication_id
                                        FROM   trophy_title_meta ttm
                                        WHERE  ttm.np_communication_id = :np_communication_id");
                                    $query->bindValue(":np_communication_id", $game, PDO::PARAM_STR);
                                    $query->execute();
                                    $mergedGame = $query->fetchColumn(); // MERGE_...
                                    if ($mergedGame) {
                                        $query = $this->database->prepare("SELECT ttm.np_communication_id
                                            FROM   trophy_title_meta ttm
                                            WHERE  ttm.parent_np_communication_id = :parent_np_communication_id AND ttm.np_communication_id != :np_communication_id");
                                        $query->bindValue(":parent_np_communication_id", $mergedGame, PDO::PARAM_STR);
                                        $query->bindValue(":np_communication_id", $game, PDO::PARAM_STR);
                                        $query->execute();
                                        $stackedGames = $query->fetchAll();

                                        $anotherStackExists = false;

                                        foreach ($stackedGames as $stackedGame) {
                                            $stackedGameId = $stackedGame["np_communication_id"];

                                            $query = $this->database->prepare("SELECT ttp.np_communication_id
                                                FROM   trophy_title_player ttp
                                                WHERE  ttp.account_id = :account_id AND ttp.np_communication_id = :np_communication_id");
                                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                            $query->bindValue(":np_communication_id", $stackedGameId, PDO::PARAM_STR);
                                            $query->execute();
                                            $stackedGameExists = $query->fetchColumn();

                                            if ($stackedGameExists) {
                                                $anotherStackExists = true;
                                            }
                                        }

                                        if (!$anotherStackExists) {
                                            $query = $this->database->prepare("DELETE FROM trophy_group_player tgp WHERE tgp.account_id = :account_id AND tgp.np_communication_id = :np_communication_id");
                                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                            $query->bindValue(":np_communication_id", $mergedGame, PDO::PARAM_STR);
                                            $query->execute();

                                            $query = $this->database->prepare("DELETE FROM trophy_title_player ttp WHERE ttp.account_id = :account_id AND ttp.np_communication_id = :np_communication_id");
                                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                            $query->bindValue(":np_communication_id", $mergedGame, PDO::PARAM_STR);
                                            $query->execute();

                                            $query = $this->database->prepare("DELETE FROM trophy_earned te WHERE te.account_id = :account_id AND te.np_communication_id = :np_communication_id");
                                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                            $query->bindValue(":np_communication_id", $mergedGame, PDO::PARAM_STR);
                                            $query->execute();
                                        }
                                    }

                                    $query = $this->database->prepare("DELETE FROM trophy_group_player tgp WHERE tgp.account_id = :account_id AND tgp.np_communication_id = :np_communication_id");
                                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                    $query->bindValue(":np_communication_id", $game, PDO::PARAM_STR);
                                    $query->execute();

                                    $query = $this->database->prepare("DELETE FROM trophy_title_player ttp WHERE ttp.account_id = :account_id AND ttp.np_communication_id = :np_communication_id");
                                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                    $query->bindValue(":np_communication_id", $game, PDO::PARAM_STR);
                                    $query->execute();

                                    $query = $this->database->prepare("DELETE FROM trophy_earned te WHERE te.account_id = :account_id AND te.np_communication_id = :np_communication_id");
                                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                    $query->bindValue(":np_communication_id", $game, PDO::PARAM_STR);
                                    $query->execute();
                                }
                            }
                        } elseif ($psnGameCount === 0 && $ourGameCount > 0) {
                            if (!($missingTrophyTitleRetry[$onlineId] ?? false)) {
                                $missingTrophyTitleRetry[$onlineId] = true;

                                $this->workerScanCoordinator->setWaitingScanProgress(
                                    (int) $worker['id'],
                                    'No trophy titles returned. Waiting 1 minute before retrying.'
                                );

                                sleep(60 * 1);
                                $recheck = '';

                                continue;
                            }

                            $this->logger->log(sprintf(
                                'Skipped deleting missing games for %s (%d) because no trophy titles were returned.',
                                (string) $player['online_id'],
                                (int) $user->accountId()
                            ));
                        }

                        // Recalculate trophy count, level & progress for the player
                        $query = $this->database->prepare("SELECT Ifnull(Sum(ttp.bronze), 0)   AS bronze,
                                Ifnull(Sum(ttp.silver), 0)   AS silver,
                                Ifnull(Sum(ttp.gold), 0)     AS gold,
                                Ifnull(Sum(ttp.platinum), 0) AS platinum
                            FROM   trophy_title_player ttp
                                JOIN trophy_title tt USING (np_communication_id)
                                JOIN trophy_title_meta ttm USING (np_communication_id)
                            WHERE  ttm.status = 0
                                AND ttp.account_id = :account_id ");
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                        $query->execute();
                        $trophies = $query->fetch();
                        $points = $trophies["bronze"]*15 + $trophies["silver"]*30 + $trophies["gold"]*90 + $trophies["platinum"]*300;
                        if ($points <= 5940) {
                            $level = floor($points / 60) + 1;
                            $progress = floor($points / 60 * 100) % 100;
                        } elseif ($points <= 14940) {
                            $level = floor(($points - 5940) / 90) + 100;
                            $progress = floor(($points - 5940) / 90 * 100) % 100;
                        } else {
                            $stage = 1;
                            $leftovers = $points - 14940;
                            while ($leftovers > 45000 * $stage) {
                                $leftovers -= 45000 * $stage;
                                $stage++;
                            }

                            $level = floor($leftovers / (450 * $stage)) + (100 + 100 * $stage);
                            $progress = floor($leftovers / (450 * $stage) * 100) % 100;
                        }

                        $query = $this->database->prepare("UPDATE player
                            SET    bronze = :bronze,
                                silver = :silver,
                                gold = :gold,
                                platinum = :platinum,
                                level = :level,
                                progress = :progress,
                                points = :points
                            WHERE  account_id = :account_id ");
                        $query->bindValue(":bronze", $trophies["bronze"], PDO::PARAM_INT);
                        $query->bindValue(":silver", $trophies["silver"], PDO::PARAM_INT);
                        $query->bindValue(":gold", $trophies["gold"], PDO::PARAM_INT);
                        $query->bindValue(":platinum", $trophies["platinum"], PDO::PARAM_INT);
                        $query->bindValue(":level", $level, PDO::PARAM_INT);
                        $query->bindValue(":progress", $progress, PDO::PARAM_INT);
                        $query->bindValue(":points", $points, PDO::PARAM_INT);
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                        $query->execute();

                        // Set player status if not a cheater
                        $playerStatus = 0;

                        // Check for hidden trophies
                        $query = $this->database->prepare("SELECT trophy_count_npwr FROM player WHERE account_id = :account_id");
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                        $query->execute();
                        $ourTotalTrophies = $query->fetchColumn();

                        if ($ourTotalTrophies > $totalTrophiesStart) { // This should never happen, but just in case... Something has gone terrible wrong...
                            $query = $this->database->prepare("UPDATE `player` SET trophy_count_npwr = (SELECT COUNT(*) FROM `trophy_earned` WHERE account_id = :account_id AND earned = 1 AND np_communication_id LIKE 'N%') WHERE account_id = :account_id");
                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                            $query->execute();

                            $query = $this->database->prepare("SELECT trophy_count_npwr FROM player WHERE account_id = :account_id");
                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                            $query->execute();
                            $ourTotalTrophies = $query->fetchColumn();

                            if (!empty($recheck)) { // Do one more scan from the beginning just to be sure.
                                continue;
                            }
                        }

                        if ($ourTotalTrophies < $totalTrophiesStart) {
                            if (!empty($recheck)) { // User seems to have hidden trophies, do one more scan from the beginning just to be sure.
                                continue;
                            }
                        }

                        // Check for inactive
                        $query = $this->database->prepare("SELECT
                                IF(
                                MAX(last_updated_date) >= DATE(NOW()) - INTERVAL 1 YEAR,
                                TRUE,
                                FALSE
                                ) AS within_a_year
                            FROM
                                `trophy_title_player`
                            WHERE
                                account_id = :account_id
                            ");
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                        $query->execute();
                        $withinAYear = $query->fetchColumn();
                        if ($withinAYear == 0) {
                            $playerStatus = 4;
                        }

                        $query = $this->database->prepare("UPDATE
                                player p
                            SET
                                p.status = :status,
                                p.trophy_count_sony = :trophy_count_sony
                            WHERE
                                p.account_id = :account_id
                                AND p.status != 1
                            ");
                        $query->bindValue(":status", $playerStatus, PDO::PARAM_INT);
                        $query->bindValue(":trophy_count_sony", $totalTrophiesStart, PDO::PARAM_INT);
                        $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                        $query->execute();
                    }

                    $query = $this->database->prepare("SELECT
                            p.status
                        FROM
                            player p
                        WHERE
                            p.account_id = :account_id");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();
                    $playerStatus = $query->fetchColumn();

                    if ($playerStatus == 0) {
                        $this->executeWithDeadlockRetry(function () use ($user): void {
                            // Update user rarity points for each game
                            $query = $this->database->prepare("WITH
                                    rarity AS(
                                    SELECT
                                        trophy_earned.np_communication_id,
                                        SUM(tm.rarity_point) AS points,
                                        SUM(tm.in_game_rarity_point) AS in_game_points,
                                        SUM(tm.rarity_name = 'COMMON') common,
                                        SUM(tm.rarity_name = 'UNCOMMON') uncommon,
                                        SUM(tm.rarity_name = 'RARE') rare,
                                        SUM(tm.rarity_name = 'EPIC') epic,
                                        SUM(tm.rarity_name = 'LEGENDARY') legendary,
                                        SUM(tm.in_game_rarity_name = 'COMMON') in_game_common,
                                        SUM(tm.in_game_rarity_name = 'UNCOMMON') in_game_uncommon,
                                        SUM(tm.in_game_rarity_name = 'RARE') in_game_rare,
                                        SUM(tm.in_game_rarity_name = 'EPIC') in_game_epic,
                                        SUM(tm.in_game_rarity_name = 'LEGENDARY') in_game_legendary
                                    FROM
                                        trophy_earned
                                    JOIN trophy t ON t.np_communication_id = trophy_earned.np_communication_id
                                        AND t.order_id = trophy_earned.order_id
                                    JOIN trophy_meta tm ON tm.trophy_id = t.id
                                    WHERE
                                        trophy_earned.account_id = :account_id AND trophy_earned.earned = 1
                                    GROUP BY
                                        trophy_earned.np_communication_id
                                    ORDER BY NULL
                                )
                                UPDATE
                                    trophy_title_player ttp,
                                    rarity
                                SET
                                    ttp.rarity_points = rarity.points,
                                    ttp.in_game_rarity_points = rarity.in_game_points,
                                    ttp.common = rarity.common,
                                    ttp.uncommon = rarity.uncommon,
                                    ttp.rare = rarity.rare,
                                    ttp.epic = rarity.epic,
                                    ttp.legendary = rarity.legendary,
                                    ttp.in_game_common = rarity.in_game_common,
                                    ttp.in_game_uncommon = rarity.in_game_uncommon,
                                    ttp.in_game_rare = rarity.in_game_rare,
                                    ttp.in_game_epic = rarity.in_game_epic,
                                    ttp.in_game_legendary = rarity.in_game_legendary
                                WHERE
                                    ttp.account_id = :account_id AND ttp.np_communication_id = rarity.np_communication_id");
                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                            $query->execute();

                            // Update user total rarity points
                            $query = $this->database->prepare("WITH
                                    rarity AS(
                                    SELECT
                                        IFNULL(SUM(rarity_points), 0) AS rarity_points,
                                        IFNULL(SUM(common), 0) AS common,
                                        IFNULL(SUM(uncommon), 0) AS uncommon,
                                        IFNULL(SUM(rare), 0) AS rare,
                                        IFNULL(SUM(epic), 0) AS epic,
                                        IFNULL(SUM(legendary), 0) AS legendary,
                                        IFNULL(SUM(in_game_rarity_points), 0) AS in_game_rarity_points,
                                        IFNULL(SUM(in_game_common), 0) AS in_game_common,
                                        IFNULL(SUM(in_game_uncommon), 0) AS in_game_uncommon,
                                        IFNULL(SUM(in_game_rare), 0) AS in_game_rare,
                                        IFNULL(SUM(in_game_epic), 0) AS in_game_epic,
                                        IFNULL(SUM(in_game_legendary), 0) AS in_game_legendary
                                    FROM
                                        trophy_title_player
                                    WHERE
                                        account_id = :account_id
                                    ORDER BY NULL
                                )
                                UPDATE
                                    player p,
                                    rarity
                                SET
                                    p.rarity_points = rarity.rarity_points,
                                    p.common = rarity.common,
                                    p.uncommon = rarity.uncommon,
                                    p.rare = rarity.rare,
                                    p.epic = rarity.epic,
                                    p.legendary = rarity.legendary,
                                    p.in_game_rarity_points = rarity.in_game_rarity_points,
                                    p.in_game_common = rarity.in_game_common,
                                    p.in_game_uncommon = rarity.in_game_uncommon,
                                    p.in_game_rare = rarity.in_game_rare,
                                    p.in_game_epic = rarity.in_game_epic,
                                    p.in_game_legendary = rarity.in_game_legendary
                                WHERE
                                    p.account_id = :account_id AND p.status = 0");
                            $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                            $query->execute();
                        });
                    }

                    // Done with the user, update the date
                    $query = $this->database->prepare("UPDATE player
                        SET    last_updated_date = Now()
                        WHERE  account_id = :account_id ");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();

                    // Delete user from the queue
                    $query = $this->database->prepare("DELETE FROM player_queue
                        WHERE  online_id = :online_id ");
                    // Don't use $user->onlineId(), since the user can have changed its name from what was entered into the queue.
                    $query->bindValue(":online_id", $user->onlineId(), PDO::PARAM_STR);
                    $query->execute();

                    unset($missingGameDeletionCheck[$onlineId]);
                    unset($missingTrophyTitleRetry[$onlineId]);
                    unset($trophyTitleCountRetry[$onlineId]);
                    $this->clearInvalidTitleDateRetriesForPlayer($invalidTitleDateRetry, $onlineId);
                }
            } catch (NotFoundHttpException $exception) {
                sleep(2);
                $recheck = '';
                unset($missingGameDeletionCheck[$onlineId]);
                unset($missingTrophyTitleRetry[$onlineId]);
                unset($trophyTitleCountRetry[$onlineId]);
                $this->clearInvalidTitleDateRetriesForPlayer($invalidTitleDateRetry, $onlineId);

                continue;
            } catch (UnauthorizedHttpException $exception) {
                sleep(2);
                $recheck = '';
                unset($missingGameDeletionCheck[$onlineId]);
                unset($missingTrophyTitleRetry[$onlineId]);
                unset($trophyTitleCountRetry[$onlineId]);
                $this->clearInvalidTitleDateRetriesForPlayer($invalidTitleDateRetry, $onlineId);

                continue;
            } finally {
                $this->workerScanCoordinator->setWorkerScanProgress((int) $worker['id'], null);
            }
        }
    }

    /**
     * @param callable(): void $operation
     */
    private function executeWithDeadlockRetry(callable $operation, int $maxAttempts = 3): void
    {
        $attempt = 0;

        while (true) {
            try {
                $operation();
                return;
            } catch (PDOException $exception) {
                if (!$this->isDeadlockException($exception) || $attempt >= $maxAttempts) {
                    throw $exception;
                }

                $attempt++;
                usleep(200000);
            }
        }
    }

    private function isDeadlockException(PDOException $exception): bool
    {
        return $exception->getCode() === '40001'
            || (($exception->errorInfo[1] ?? null) === 1213);
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
            if ($this->determineStatusCode($exception) === 404) {
                return null;
            }

            throw $exception;
        }

        $normalized = $this->normalizePlayerProfileResponse($profile);

        return is_array($normalized) ? $normalized : null;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function determineResolvedOnlineId(array $profile, string $fallbackOnlineId): string
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

    private function extractCountryFromNpId(mixed $npId): ?string
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

    private function markPlayerAsPrivate(string $onlineId): void
    {
        $query = $this->database->prepare(
            'UPDATE
                player
            SET
                `status` = 3,
                last_updated_date = NOW()
            WHERE
                online_id = :online_id
                AND `status` != 1'
        );
        $query->bindValue(':online_id', $onlineId, PDO::PARAM_STR);
        $query->execute();

        $query = $this->database->prepare(
            'DELETE FROM player_queue WHERE online_id = :online_id'
        );
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

    /**
     * @param mixed $accountId
     */
    private function normalizeAccountIdValue($accountId): ?string
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

    private function fetchStoredCountryByAccountId(int $accountId): ?string
    {
        $query = $this->database->prepare(
            'SELECT country FROM player WHERE account_id = :account_id'
        );
        $query->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $query->execute();

        $country = $query->fetchColumn();

        if (!is_string($country) || $country === '') {
            return null;
        }

        return $country;
    }

    private function determineStatusCode(Throwable $exception): ?int
    {
        $response = $this->findResponseFromThrowable($exception);

        if ($response !== null) {
            $status = $this->extractStatusCodeFromResponse($response);

            if ($status !== null) {
                return $status;
            }
        }

        return $this->extractStatusCodeFromThrowable($exception);
    }

    private function findResponseFromThrowable(Throwable $exception): ?object
    {
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();

            if (is_object($response)) {
                return $response;
            }
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->findResponseFromThrowable($previous);
        }

        return null;
    }

    private function extractStatusCodeFromResponse(object $response): ?int
    {
        if (method_exists($response, 'getStatusCode')) {
            $statusCode = $response->getStatusCode();

            if (is_int($statusCode)) {
                return $statusCode;
            }
        }

        if (method_exists($response, 'getStatus')) {
            $status = $response->getStatus();

            if (is_int($status)) {
                return $status;
            }
        }

        return null;
    }

    private function extractStatusCodeFromThrowable(Throwable $exception): ?int
    {
        $code = $exception->getCode();

        if (is_int($code) && $code > 0) {
            return $code;
        }

        $previous = $exception->getPrevious();

        if ($previous instanceof Throwable) {
            return $this->extractStatusCodeFromThrowable($previous);
        }

        return null;
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

    private function findTrophyTitleId(string $npCommunicationId): ?int
    {
        $query = $this->database->prepare(
            'SELECT id FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $id = $query->fetchColumn();

        if ($id === false) {
            return null;
        }

        return (int) $id;
    }

    private function ensureTrophyMetaRow(string $npCommunicationId, string $groupId, int $orderId): void
    {
        $this->trophyMetaRepository->ensureExists($npCommunicationId, $groupId, $orderId);
    }

    private function gameTimestampsMatch(string $sonyTimestamp, string $dbTimestamp): bool
    {
        $sonyLastUpdatedDate = $this->parseDateTime($sonyTimestamp);

        if ($sonyLastUpdatedDate === null) {
            return false;
        }

        $dbLastUpdatedDate = $this->parseDateTime($dbTimestamp);

        if ($dbLastUpdatedDate === null) {
            return false;
        }

        return $sonyLastUpdatedDate == $dbLastUpdatedDate;
    }

    private function isValidSonyLastUpdatedDateTime(string $value): bool
    {
        return $this->formatDateTimeForDatabase($value) !== null;
    }

    private function buildInvalidTitleDateRetryKey(string $onlineId, string $npCommunicationId): string
    {
        return $onlineId . ':' . $npCommunicationId;
    }

    /**
     * @param array<string, bool> $retryTracker
     */
    private function shouldRetryInvalidTitleLastUpdatedDate(
        array $retryTracker,
        string $onlineId,
        string $npCommunicationId
    ): bool {
        return !($retryTracker[$this->buildInvalidTitleDateRetryKey($onlineId, $npCommunicationId)] ?? false);
    }

    /**
     * @param array<string, bool> $retryTracker
     */
    private function markInvalidTitleLastUpdatedDateRetried(
        array &$retryTracker,
        string $onlineId,
        string $npCommunicationId
    ): void {
        $retryTracker[$this->buildInvalidTitleDateRetryKey($onlineId, $npCommunicationId)] = true;
    }

    /**
     * @param array<string, bool> $retryTracker
     */
    private function clearInvalidTitleDateRetriesForPlayer(array &$retryTracker, string $onlineId): void
    {
        $prefix = $onlineId . ':';

        foreach (array_keys($retryTracker) as $retryKey) {
            if (str_starts_with($retryKey, $prefix)) {
                unset($retryTracker[$retryKey]);
            }
        }
    }

    private function formatDateTimeForDatabase(?string $value): ?string
    {
        $dateTime = $this->parseDateTime($value);

        return $dateTime?->format('Y-m-d H:i:s');
    }

    private function parseDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function isIncomingSetVersionOlderThanStored(string $newVersion, mixed $currentVersion): bool
    {
        $normalizedCurrentVersion = $this->normalizeSetVersion($currentVersion);

        if ($normalizedCurrentVersion === null) {
            return false;
        }

        return version_compare(trim($newVersion), $normalizedCurrentVersion, '<');
    }

    private function resolveSetVersionForUpdate(string $newVersion, mixed $currentVersion): string
    {
        $normalizedCurrentVersion = $this->normalizeSetVersion($currentVersion);

        if ($normalizedCurrentVersion === null) {
            return trim($newVersion);
        }

        if (version_compare(trim($newVersion), $normalizedCurrentVersion, '<')) {
            return $normalizedCurrentVersion;
        }

        return trim($newVersion);
    }

    private function normalizeSetVersion(mixed $version): ?string
    {
        if (!is_string($version)) {
            return null;
        }

        $trimmedVersion = trim($version);

        if ($trimmedVersion === '') {
            return null;
        }

        return $trimmedVersion;
    }
}