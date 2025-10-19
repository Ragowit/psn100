<?php

declare(strict_types=1);

require_once __DIR__ . '/GameRescanProgressListener.php';

use Tustin\PlayStation\Client;

class GameRescanService
{
    private const TITLE_ICON_DIRECTORY = '/home/psn100/public_html/img/title/';
    private const GROUP_ICON_DIRECTORY = '/home/psn100/public_html/img/group/';
    private const TROPHY_ICON_DIRECTORY = '/home/psn100/public_html/img/trophy/';
    private const REWARD_ICON_DIRECTORY = '/home/psn100/public_html/img/reward/';
    private const ORIGINAL_GAME_PREFIX = 'NPWR';
    private const LOGIN_RETRY_DELAY_SECONDS = 300;

    private PDO $database;
    private TrophyCalculator $trophyCalculator;
    private int $lastProgress = 0;

    /**
     * @var callable(string):void|null
     */
    private $logListener = null;

    public function __construct(PDO $database, TrophyCalculator $trophyCalculator)
    {
        $this->database = $database;
        $this->trophyCalculator = $trophyCalculator;
    }

    /**
     * @param callable(string):void|null $logListener
     */
    public function rescan(
        int $gameId,
        ?GameRescanProgressListener $progressListener = null,
        ?callable $logListener = null
    ): string {
        $previousLogListener = $this->logListener;
        $this->logListener = $logListener;

        try {
            $this->lastProgress = 0;
            $this->notifyProgress($progressListener, 5, 'Validating game id…');
            $npCommunicationId = $this->getGameNpCommunicationId($gameId);

            $this->notifyProgress($progressListener, 10, 'Checking game entry…');

            if (!$this->isOriginalGame($npCommunicationId)) {
                return 'Can only rescan original game entries.';
            }

            $this->notifyProgress($progressListener, 15, 'Signing in to worker account…');
            $client = $this->loginToWorker();
            $this->notifyProgress($progressListener, 20, 'Locating accessible player…');
            $user = $this->findAccessibleUserWithGame($client, $npCommunicationId);

            if ($user === null) {
                throw new RuntimeException('Unable to find accessible player for the specified game.');
            }

            $trophyTitle = $this->findTrophyTitleForUser($user, $npCommunicationId);

            if ($trophyTitle === null) {
                throw new RuntimeException('Unable to find trophy title for the specified game.');
            }

            $this->notifyProgress($progressListener, 25, 'Refreshing trophy details…');
            $trophyGroups = $this->updateTrophyTitle($client, $trophyTitle, $npCommunicationId, $progressListener);
            $this->notifyProgress($progressListener, 70, 'Recalculating player statistics…');
            $this->recalculateTrophies(
                $trophyTitle,
                $npCommunicationId,
                (int) $user->accountId(),
                $trophyGroups,
                $progressListener
            );

            $this->notifyProgress($progressListener, 85, 'Updating trophy set version…');
            $this->updateTrophySetVersion($npCommunicationId, $trophyTitle->trophySetVersion());
            $this->notifyProgress($progressListener, 90, 'Recording rescan details…');
            $this->recordRescan($gameId);

            return "Game {$gameId} have been rescanned.";
        } finally {
            $this->logListener = $previousLogListener;
        }
    }

    private function getGameNpCommunicationId(int $gameId): string
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

    private function isOriginalGame(string $npCommunicationId): bool
    {
        return str_starts_with($npCommunicationId, self::ORIGINAL_GAME_PREFIX);
    }

    private function loginToWorker(): Client
    {
        while (true) {
            foreach ($this->fetchWorkers() as $worker) {
                try {
                    $client = new Client();
                    $client->loginWithNpsso($worker['npsso']);

                    return $client;
                } catch (TypeError $exception) {
                    // Something odd, try next worker.
                } catch (Exception $exception) {
                    $this->logMessage("Can't login with worker " . $worker['id']);
                }
            }

            sleep(self::LOGIN_RETRY_DELAY_SECONDS);
        }
    }

    private function fetchWorkers(): array
    {
        $query = $this->database->prepare('SELECT id, npsso FROM setting ORDER BY id');
        $query->execute();

        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    private function logMessage(string $message): void
    {
        if ($this->logListener !== null) {
            ($this->logListener)($message);

            return;
        }

        $query = $this->database->prepare('INSERT INTO log(message) VALUES(:message)');
        $query->bindValue(':message', $message, PDO::PARAM_STR);
        $query->execute();
    }

    private function findAccessibleUserWithGame(Client $client, string $npCommunicationId): ?object
    {
        $query = $this->database->prepare(
            'SELECT account_id
            FROM trophy_title_player ttp
            JOIN player p USING(account_id)
            WHERE ttp.np_communication_id = :np_communication_id AND p.status != 3
            ORDER BY ttp.last_updated_date DESC'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        while (($accountId = $query->fetchColumn()) !== false) {
            $user = $client->users()->find((string) $accountId);

            try {
                $user->trophySummary()->level();

                return $user;
            } catch (TypeError $exception) {
                // Something odd, try next player.
            } catch (Exception $exception) {
                // Player probably private, try next player.
            }
        }

        return null;
    }

    private function notifyProgress(?GameRescanProgressListener $progressListener, int $percent, string $message): void
    {
        if ($progressListener === null) {
            return;
        }

        $clampedPercent = max(0, min(100, $percent));
        if ($clampedPercent < $this->lastProgress) {
            $clampedPercent = $this->lastProgress;
        } else {
            $this->lastProgress = $clampedPercent;
        }

        $progressListener->onProgress($clampedPercent, $message);
    }

    private function notifyProgressRange(
        ?GameRescanProgressListener $progressListener,
        int $startPercent,
        int $endPercent,
        int $step,
        int $totalSteps,
        string $message
    ): void {
        if ($progressListener === null || $totalSteps <= 0) {
            return;
        }

        $boundedStep = max(0, min($totalSteps, $step));
        $progressSpan = $endPercent - $startPercent;

        if ($progressSpan === 0) {
            $targetPercent = $startPercent;
        } else {
            $progressRatio = $boundedStep / $totalSteps;
            $interpolated = $startPercent + ($progressSpan * $progressRatio);

            if ($boundedStep === $totalSteps) {
                $interpolated = $endPercent;
            }

            $targetPercent = (int) floor($interpolated);

            if ($progressSpan > 0) {
                $targetPercent = min($targetPercent, $endPercent);
            } else {
                $targetPercent = max($targetPercent, $endPercent);
            }
        }

        $this->notifyProgress($progressListener, $targetPercent, $message);
    }

    private function describeTrophyGroup(object $trophyGroup): string
    {
        $name = $this->normalizeProgressLabel((string) $trophyGroup->name());
        if ($name !== '') {
            return $name;
        }

        $detail = $this->normalizeProgressLabel((string) $trophyGroup->detail());
        if ($detail !== '') {
            return $detail;
        }

        return sprintf('Group %s', $this->normalizeProgressLabel((string) $trophyGroup->id()) ?: (string) $trophyGroup->id());
    }

    private function describeTrophy(object $trophy): string
    {
        $name = $this->normalizeProgressLabel((string) $trophy->name());
        if ($name !== '') {
            return $name;
        }

        $detail = $this->normalizeProgressLabel((string) $trophy->detail());
        if ($detail !== '') {
            return $detail;
        }

        return sprintf('Trophy %s', $this->normalizeProgressLabel((string) $trophy->id()) ?: (string) $trophy->id());
    }

    private function normalizeProgressLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/', ' ', $normalized);
        if (!is_string($normalized)) {
            return '';
        }

        return $normalized;
    }

    private function findTrophyTitleForUser(object $user, string $npCommunicationId): ?object
    {
        foreach ($user->trophyTitles() as $trophyTitle) {
            if ($trophyTitle->npCommunicationId() === $npCommunicationId) {
                return $trophyTitle;
            }
        }

        return null;
    }

    private function updateTrophyTitle(
        Client $client,
        object $trophyTitle,
        string $npCommunicationId,
        ?GameRescanProgressListener $progressListener
    ): array {
        $existingTitleIcon = $this->fetchExistingTitleIcon($npCommunicationId);
        $existingGroupIcons = $this->fetchExistingTrophyGroupIcons($npCommunicationId);
        $existingTrophyAssets = $this->fetchExistingTrophyAssets($npCommunicationId);

        $titleIconFilename = $this->downloadImage(
            $trophyTitle->iconUrl(),
            self::TITLE_ICON_DIRECTORY,
            $existingTitleIcon
        );
        $platforms = $this->buildPlatformList($trophyTitle);

        $query = $this->database->prepare(
            'UPDATE trophy_title
            SET detail = :detail,
                icon_url = :icon_url,
                platform = :platform
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':detail', $trophyTitle->detail(), PDO::PARAM_STR);
        $query->bindValue(':icon_url', $titleIconFilename, PDO::PARAM_STR);
        $query->bindValue(':platform', $platforms, PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $trophies = $client->trophies($npCommunicationId, $trophyTitle->serviceName());
        $groupData = [];
        $totalSteps = 0;

        foreach ($trophies->trophyGroups() as $trophyGroup) {
            $trophiesInGroup = [];
            foreach ($trophyGroup->trophies() as $trophy) {
                $trophiesInGroup[] = $trophy;
            }

            $groupData[] = [
                'group' => $trophyGroup,
                'trophies' => $trophiesInGroup,
            ];

            $totalSteps += 1 + count($trophiesInGroup);
        }

        if ($groupData === []) {
            $this->notifyProgress($progressListener, 70, 'Refreshing trophy details…');

            return [];
        }

        $currentStep = 0;
        $totalGroups = count($groupData);
        $processedGroups = 0;

        foreach ($groupData as $data) {
            $trophyGroup = $data['group'];
            $groupIconFilename = $this->downloadImage(
                $trophyGroup->iconUrl(),
                self::GROUP_ICON_DIRECTORY,
                $existingGroupIcons[$trophyGroup->id()] ?? null
            );
            $this->upsertTrophyGroup($npCommunicationId, $trophyGroup, $groupIconFilename);

            $processedGroups++;
            $groupLabel = $this->describeTrophyGroup($trophyGroup);
            $currentStep++;
            $this->notifyProgressRange(
                $progressListener,
                25,
                70,
                $currentStep,
                $totalSteps,
                sprintf(
                    'Refreshing trophy group "%s" (%d/%d groups)',
                    $groupLabel,
                    $processedGroups,
                    $totalGroups
                )
            );

            $groupTrophyCount = count($data['trophies']);
            $processedTrophiesInGroup = 0;

            foreach ($data['trophies'] as $trophy) {
                $existingTrophyAsset = $existingTrophyAssets[$trophyGroup->id()][(int) $trophy->id()] ?? [
                    'icon' => null,
                    'reward' => null,
                ];

                $trophyIconFilename = $this->downloadImage(
                    $trophy->iconUrl(),
                    self::TROPHY_ICON_DIRECTORY,
                    $existingTrophyAsset['icon']
                );
                $rewardImageFilename = $this->downloadOptionalImage(
                    $trophy->rewardImageUrl(),
                    self::REWARD_ICON_DIRECTORY,
                    $existingTrophyAsset['reward']
                );
                $this->upsertTrophy($npCommunicationId, $trophyGroup->id(), $trophy, $trophyIconFilename, $rewardImageFilename);

                $processedTrophiesInGroup++;
                $currentStep++;
                $this->notifyProgressRange(
                    $progressListener,
                    25,
                    70,
                    $currentStep,
                    $totalSteps,
                    sprintf(
                        'Refreshing trophy "%s" in group "%s" (%d/%d trophies, group %d/%d)',
                        $this->describeTrophy($trophy),
                        $groupLabel,
                        $processedTrophiesInGroup,
                        max(1, $groupTrophyCount),
                        $processedGroups,
                        $totalGroups
                    )
                );
            }
        }

        return array_map(
            static fn (array $data) => $data['group'],
            $groupData
        );
    }

    private function recalculateTrophies(
        object $trophyTitle,
        string $npCommunicationId,
        int $accountId,
        array $trophyGroups,
        ?GameRescanProgressListener $progressListener
    ): void {
        $baseMessage = 'Recalculating player statistics…';
        $totalGroups = count($trophyGroups);
        $currentGroup = 0;

        foreach ($trophyGroups as $trophyGroup) {
            $this->trophyCalculator->recalculateTrophyGroup($npCommunicationId, $trophyGroup->id(), $accountId);

            $currentGroup++;
            $this->notifyProgressRange(
                $progressListener,
                70,
                82,
                $currentGroup,
                $totalGroups,
                sprintf('%s (%d/%d)', $baseMessage, $currentGroup, $totalGroups)
            );
        }

        $this->trophyCalculator->recalculateTrophyTitle(
            $npCommunicationId,
            $trophyTitle->lastUpdatedDateTime(),
            true,
            $accountId,
            false
        );

        $this->notifyProgress($progressListener, 83, $baseMessage);

        $this->recalculateParentTitles(
            $npCommunicationId,
            $trophyTitle->lastUpdatedDateTime(),
            $accountId,
            $progressListener
        );

        $this->notifyProgress($progressListener, 84, $baseMessage);
    }

    private function upsertTrophyGroup(string $npCommunicationId, object $trophyGroup, string $iconFilename): void
    {
        $query = $this->database->prepare(
            'INSERT INTO trophy_group (
                np_communication_id,
                group_id,
                name,
                detail,
                icon_url
            )
            VALUES (
                :np_communication_id,
                :group_id,
                :name,
                :detail,
                :icon_url
            ) AS new
            ON DUPLICATE KEY UPDATE
                detail = new.detail,
                icon_url = new.icon_url'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $trophyGroup->id(), PDO::PARAM_STR);
        $query->bindValue(':name', $trophyGroup->name(), PDO::PARAM_STR);
        $query->bindValue(':detail', $trophyGroup->detail(), PDO::PARAM_STR);
        $query->bindValue(':icon_url', $iconFilename, PDO::PARAM_STR);
        $query->execute();
    }

    private function upsertTrophy(
        string $npCommunicationId,
        string $groupId,
        object $trophy,
        string $iconFilename,
        ?string $rewardImageFilename
    ): void {
        $query = $this->database->prepare(
            'INSERT INTO trophy (
                np_communication_id,
                group_id,
                order_id,
                hidden,
                type,
                name,
                detail,
                icon_url,
                progress_target_value,
                reward_name,
                reward_image_url
            )
            VALUES (
                :np_communication_id,
                :group_id,
                :order_id,
                :hidden,
                :type,
                :name,
                :detail,
                :icon_url,
                :progress_target_value,
                :reward_name,
                :reward_image_url
            ) AS new
            ON DUPLICATE KEY UPDATE
                hidden = new.hidden,
                type = new.type,
                name = new.name,
                detail = new.detail,
                icon_url = new.icon_url,
                progress_target_value = new.progress_target_value,
                reward_name = new.reward_name,
                reward_image_url = new.reward_image_url'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_STR);
        $query->bindValue(':order_id', $trophy->id(), PDO::PARAM_INT);
        $query->bindValue(':hidden', $trophy->hidden(), PDO::PARAM_INT);
        $query->bindValue(':type', $trophy->type()->value, PDO::PARAM_STR);
        $query->bindValue(':name', $trophy->name(), PDO::PARAM_STR);
        $query->bindValue(':detail', $trophy->detail(), PDO::PARAM_STR);
        $query->bindValue(':icon_url', $iconFilename, PDO::PARAM_STR);

        $progressTargetValue = $trophy->progressTargetValue();
        if ($progressTargetValue === '') {
            $this->bindNullable($query, ':progress_target_value', null);
        } else {
            $query->bindValue(':progress_target_value', (int) $progressTargetValue, PDO::PARAM_INT);
        }

        $rewardName = $trophy->rewardName();
        if ($rewardName === '') {
            $this->bindNullable($query, ':reward_name', null);
        } else {
            $query->bindValue(':reward_name', $rewardName, PDO::PARAM_STR);
        }

        $this->bindNullable($query, ':reward_image_url', $rewardImageFilename);

        $query->execute();
    }

    private function bindNullable(PDOStatement $query, string $parameter, ?string $value): void
    {
        if ($value === null) {
            $query->bindValue($parameter, null, PDO::PARAM_NULL);

            return;
        }

        $query->bindValue($parameter, $value, PDO::PARAM_STR);
    }

    private function recalculateParentTitles(
        string $childNpCommunicationId,
        string $lastUpdatedDateTime,
        int $accountId,
        ?GameRescanProgressListener $progressListener = null
    ): void {
        $query = $this->database->prepare(
            'SELECT DISTINCT parent_np_communication_id, parent_group_id
            FROM trophy_merge
            WHERE child_np_communication_id = :child_np_communication_id'
        );
        $query->bindValue(':child_np_communication_id', $childNpCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
        $totalParents = count($rows);
        $currentParent = 0;

        foreach ($rows as $row) {
            $parentNpCommunicationId = (string) $row['parent_np_communication_id'];
            $parentGroupId = (string) $row['parent_group_id'];

            $this->trophyCalculator->recalculateTrophyGroup($parentNpCommunicationId, $parentGroupId, $accountId);
            $this->trophyCalculator->recalculateTrophyTitle(
                $parentNpCommunicationId,
                $lastUpdatedDateTime,
                false,
                $accountId,
                true
            );

            $currentParent++;
            $this->notifyProgressRange(
                $progressListener,
                83,
                84,
                $currentParent,
                $totalParents,
                sprintf('Recalculating merged trophy titles… (%d/%d)', $currentParent, $totalParents)
            );
        }
    }

    private function updateTrophySetVersion(string $npCommunicationId, string $setVersion): void
    {
        $query = $this->database->prepare(
            'UPDATE trophy_title
            SET set_version = :set_version
            WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':set_version', $setVersion, PDO::PARAM_STR);
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();
    }

    private function recordRescan(int $gameId): void
    {
        $query = $this->database->prepare(
            "INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_RESCAN', :param_1)"
        );
        $query->bindValue(':param_1', $gameId, PDO::PARAM_INT);
        $query->execute();
    }

    private function fetchExistingTitleIcon(string $npCommunicationId): ?string
    {
        $query = $this->database->prepare(
            'SELECT icon_url FROM trophy_title WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $iconUrl = $query->fetchColumn();

        return $iconUrl === false ? null : (string) $iconUrl;
    }

    private function fetchExistingTrophyGroupIcons(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT group_id, icon_url FROM trophy_group WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $icons = [];
        while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
            $groupId = (string) $row['group_id'];
            $iconUrl = $row['icon_url'];
            $icons[$groupId] = $iconUrl === null ? null : (string) $iconUrl;
        }

        return $icons;
    }

    private function fetchExistingTrophyAssets(string $npCommunicationId): array
    {
        $query = $this->database->prepare(
            'SELECT group_id, order_id, icon_url, reward_image_url'
            . ' FROM trophy WHERE np_communication_id = :np_communication_id'
        );
        $query->bindValue(':np_communication_id', $npCommunicationId, PDO::PARAM_STR);
        $query->execute();

        $assets = [];
        while (($row = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
            $groupId = (string) $row['group_id'];
            $orderId = (int) $row['order_id'];
            $assets[$groupId][$orderId] = [
                'icon' => $row['icon_url'] === null ? null : (string) $row['icon_url'],
                'reward' => $row['reward_image_url'] === null ? null : (string) $row['reward_image_url'],
            ];
        }

        return $assets;
    }

    private function downloadImage(string $url, string $directory, ?string $existingFilename = null): string
    {
        $contents = $this->fetchRemoteFile($url);

        if ($contents === null) {
            if ($existingFilename !== null && $existingFilename !== '') {
                $this->logMessage(
                    sprintf('Reusing cached image "%s" because "%s" is unavailable.', $existingFilename, $url)
                );

                return $existingFilename;
            }

            $this->logMessage(sprintf('Unable to download image from "%s".', $url));

            throw new RuntimeException(sprintf('Unable to download image from "%s".', $url));
        }

        $filename = $this->buildFilename($url, $contents);
        $path = $directory . $filename;

        if (!file_exists($path)) {
            file_put_contents($path, $contents);
        }

        return $filename;
    }

    private function downloadOptionalImage(?string $url, string $directory, ?string $existingFilename = null): ?string
    {
        if ($url === null || $url === '') {
            return $existingFilename;
        }

        $contents = $this->fetchRemoteFile($url);

        if ($contents === null) {
            if ($existingFilename !== null && $existingFilename !== '') {
                $this->logMessage(
                    sprintf('Keeping cached optional image "%s" because "%s" is unavailable.', $existingFilename, $url)
                );
            }

            return $existingFilename;
        }

        $filename = $this->buildFilename($url, $contents);
        $path = $directory . $filename;

        if (!file_exists($path)) {
            file_put_contents($path, $contents);
        }

        return $filename;
    }

    private function fetchRemoteFile(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $contents = @file_get_contents($url, false, $context);
        if ($contents === false) {
            return null;
        }

        $statusLine = $http_response_header[0] ?? '';
        if ($statusLine !== '' && !preg_match('/^HTTP\/\S+\s+2\d\d\b/', $statusLine)) {
            return null;
        }

        return $contents;
    }

    private function buildFilename(string $url, string $contents): string
    {
        $hash = md5($contents);
        $extensionPosition = strrpos($url, '.');
        $extension = $extensionPosition === false ? '' : strtolower(substr($url, $extensionPosition));

        return $hash . $extension;
    }

    private function buildPlatformList(object $trophyTitle): string
    {
        $platforms = [];
        foreach ($trophyTitle->platform() as $platform) {
            $platforms[] = $platform->value;
        }

        return implode(',', $platforms);
    }
}
