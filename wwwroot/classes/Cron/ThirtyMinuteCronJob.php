<?php

declare(strict_types=1);

require_once __DIR__ . '/CronJobInterface.php';

use Tustin\PlayStation\Client;

class ThirtyMinuteCronJob implements CronJobInterface
{
    private const TITLE_ICON_DIRECTORY = '/home/psn100/public_html/img/title/';
    private const GROUP_ICON_DIRECTORY = '/home/psn100/public_html/img/group/';
    private const TROPHY_ICON_DIRECTORY = '/home/psn100/public_html/img/trophy/';
    private const REWARD_ICON_DIRECTORY = '/home/psn100/public_html/img/reward/';

    private PDO $database;

    private TrophyCalculator $trophyCalculator;

    private Psn100Logger $logger;

    private int $workerId;

    public function __construct(PDO $database, TrophyCalculator $trophyCalculator, Psn100Logger $logger, int $workerId)
    {
        $this->database = $database;
        $this->trophyCalculator = $trophyCalculator;
        $this->logger = $logger;
        $this->workerId = $workerId;
    }

    public function run(): void
    {
        $recheck = "";

        while (true) {
            // Login with a token
            $loggedIn = false;
            while (!$loggedIn) {
                $query = $this->database->prepare("SELECT
                        id,
                        npsso,
                        scanning
                    FROM
                        setting
                    WHERE
                        id = :id");
                $query->bindValue(":id", $this->workerId, PDO::PARAM_INT);
                $query->execute();
                $worker = $query->fetch();

                try {
                    $client = new Client();
                    $npsso = $worker["npsso"];
                    $client->loginWithNpsso($npsso);

                    $loggedIn = true;
                } catch (TypeError $e) {
                    // Something odd, let's wait three minutes
                    sleep(60 * 3);
                } catch (Exception $e) {
                    $this->logger->log("Can't login with worker ". $worker["id"]);

                    // Something went wrong, 'release' the current scanning profile so other workers can pick it up.
                    $query = $this->database->prepare("UPDATE setting SET scanning = :id WHERE id = :id");
                    $query->bindValue(":id", $worker["id"], PDO::PARAM_INT);
                    $query->execute();

                    // Wait 30 minutes to not hammer login
                    sleep(60 * 30);
                }
            }

            try {
                // Get our queue.
                // #1 - Users added from the front page, ordered by time entered
                // #2 - Top 100 players who haven't been updated within a day, ordered by the oldest one.
                // #3 - Top 1000 players or +/- 250 players who are about to drop out of top 10k who haven't been updated within a week, ordered by the oldest one.
                // #4 - Top 10000 players who haven't been updated within a month, ordered by the oldest one.
                // #5 - Oldest scanned player who is not tagged as a cheater
                $query = $this->database->prepare("
                    WITH
                        now_values AS (
                            SELECT
                                NOW() AS now,
                                NOW() - INTERVAL 1 HOUR AS cutoff_1h,
                                NOW() - INTERVAL 1 DAY AS cutoff_1d,
                                NOW() - INTERVAL 1 WEEK AS cutoff_1w
                        )
                    SELECT
                        online_id,
                        account_id
                    FROM (
                        SELECT
                            1 AS tier,
                            pq.online_id,
                            pq.request_time AS priority_timestamp,
                            p.account_id
                        FROM
                            player_queue pq
                            LEFT JOIN player p ON p.online_id = pq.online_id

                        UNION ALL

                        SELECT
                            2 AS tier,
                            p.online_id,
                            p.last_updated_date AS priority_timestamp,
                            p.account_id
                        FROM
                            player p
                            JOIN player_ranking pr ON pr.account_id = p.account_id
                            JOIN now_values nv
                        WHERE
                            (pr.ranking <= 100 OR pr.rarity_ranking <= 100)
                            AND p.last_updated_date < nv.cutoff_1h

                        UNION ALL

                        SELECT
                            3 AS tier,
                            p.online_id,
                            p.last_updated_date AS priority_timestamp,
                            p.account_id
                        FROM
                            player p
                            JOIN player_ranking pr ON pr.account_id = p.account_id
                            JOIN now_values nv
                        WHERE
                            (
                                pr.ranking <= 1000 OR
                                pr.rarity_ranking <= 1000 OR
                                (pr.ranking BETWEEN 9750 AND 10250) OR
                                (pr.rarity_ranking BETWEEN 9750 AND 10250)
                            )
                            AND p.last_updated_date < nv.cutoff_1d

                        UNION ALL

                        SELECT
                            4 AS tier,
                            p.online_id,
                            p.last_updated_date AS priority_timestamp,
                            p.account_id
                        FROM
                            player p
                            JOIN player_ranking pr ON pr.account_id = p.account_id
                            JOIN now_values nv
                        WHERE
                            (pr.ranking <= 10000 OR pr.rarity_ranking <= 10000)
                            AND p.last_updated_date < nv.cutoff_1w

                        UNION ALL

                        SELECT
                            5 AS tier,
                            p.online_id,
                            p.last_updated_date AS priority_timestamp,
                            p.account_id
                        FROM
                            player p
                        WHERE
                            p.status != 1
                    ) a
                    WHERE NOT EXISTS (
                        SELECT 1 FROM setting s
                        WHERE s.scanning = a.online_id AND s.id != :worker_id
                    )
                    ORDER BY
                        tier,
                        priority_timestamp,
                        online_id
                    LIMIT 1
                ");
                $query->bindValue(":worker_id", $worker["id"], PDO::PARAM_INT);
                $query->execute();
                $player = $query->fetch();

                $query = $this->database->prepare("UPDATE setting SET scanning = :scanning WHERE id = :worker_id");
                $query->bindValue(":scanning", $player["online_id"], PDO::PARAM_STR);
                $query->bindValue(":worker_id", $worker["id"], PDO::PARAM_INT);
                $query->execute();
            } catch (Exception $e) {
                // Probably just an exception for "Integrity constraint violation: 1062 Duplicate entry 'online_id' for key 'setting.scanning'" because another thread was faster then this one
                // Continue and try again!
                continue;
            }

            if ($recheck == $player["online_id"]) {
                $recheck = "";
            } else {
                $recheck = $player["online_id"];
            }

            // Initialize the current player
            try {
                if (is_numeric($player["account_id"])) {
                    $user = $client->users()->find((string) $player["account_id"]);
                    // ->find() doesn't have country information, but we should have it in our database from the ->search() when user was new to us.
                    $query = $this->database->prepare("SELECT
                            country
                        FROM
                            player
                        WHERE
                            account_id = :account_id
                    ");
                    $query->bindValue(":account_id", $player["account_id"], PDO::PARAM_INT);
                    $query->execute();
                    $country = $query->fetchColumn();
                } else {
                    // Search the user
                    unset($user);
                    $userFound = false;
                    $userCounter = 0;

                    foreach ($client->users()->search($player["online_id"]) as $userSearchResult) {
                        if (strtolower($userSearchResult->onlineId()) == strtolower($player["online_id"])) {
                            $user = $userSearchResult;
                            $userFound = true;
                            $country = $user->country();
                            break;
                        }

                        // Limit to the first 50 search results
                        $userCounter++;
                        if ($userCounter >= 50) {
                            break;
                        }
                    }

                    if (!$userFound) {
                        // User not found, set as private and remove from queue.
                        $query = $this->database->prepare("UPDATE
                                player
                            SET
                                `status` = 3,
                                last_updated_date = NOW()
                            WHERE
                                online_id = :online_id
                                AND `status` != 1
                            ");
                        $query->bindValue(":online_id", $player["online_id"], PDO::PARAM_STR);
                        $query->execute();

                        $query = $this->database->prepare("DELETE
                            FROM
                                player_queue
                            WHERE
                                online_id = :online_id");
                        $query->bindValue(":online_id", $player["online_id"], PDO::PARAM_STR);
                        $query->execute();

                        continue;
                    }
                }

                // To test for exception (and apparently collects/updates to new onlineId if changed).
                $user->aboutMe();

                if (strtolower($player["online_id"]) != strtolower($user->onlineId())) {
                    $query = $this->database->prepare("UPDATE player_queue SET online_id = :online_id_new WHERE online_id = :online_id_old");
                    $query->bindValue(":online_id_new", $user->onlineId(), PDO::PARAM_STR);
                    $query->bindValue(":online_id_old", $player["online_id"], PDO::PARAM_STR);
                    $query->execute();

                    $query = $this->database->prepare("UPDATE setting SET scanning = :scanning WHERE id = :worker_id");
                    $query->bindValue(":scanning", $user->onlineId(), PDO::PARAM_STR);
                    $query->bindValue(":worker_id", $worker["id"], PDO::PARAM_INT);
                    $query->execute();
                }
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

                continue;
            }

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
                    $md5Hash = md5_file($avatarUrl);
                    if (!$md5Hash) {
                        // File not found. Try next.
                        continue;
                    }

                    $extension = strtolower(pathinfo($avatarUrl, PATHINFO_EXTENSION));

                    $avatarFilename = $md5Hash .".". $extension;
                    if (!file_exists("/home/psn100/public_html/img/avatar/". $avatarFilename)) {
                        file_put_contents("/home/psn100/public_html/img/avatar/". $avatarFilename, fopen($avatarUrl, 'r'));
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
                    $query->bindValue(":md5_hash", $md5Hash, PDO::PARAM_STR);
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
                sleep(60 * 5);

                // Something is odd with PSN, break out and try again later.
                break;
            }

            $privateUser = false;
            try {
                $level = 0;
                $level = $user->trophySummary()->level();
            } catch (TypeError $e) {
                // Rare error, wait 1 minute to not hammer Sony and try again.
                sleep(60 * 1);
                break;
            } catch (Exception $e) {
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

            if (!$privateUser) {
                $totalTrophiesStart = $user->trophySummary()->platinum() + $user->trophySummary()->gold() + $user->trophySummary()->silver() + $user->trophySummary()->bronze();

                if ($level !== 0) {
                    $query = $this->database->prepare("SELECT p.last_updated_date, p.trophy_count_npwr, p.trophy_count_sony
                        FROM   player p
                        WHERE  p.account_id = :account_id ");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();
                    $playerData = $query->fetch();

                    $query = $this->database->prepare("SELECT np_communication_id,
                            last_updated_date
                        FROM   trophy_title_player
                        WHERE  account_id = :account_id ");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();
                    $gameLastUpdatedDate = $query->fetchAll(PDO::FETCH_KEY_PAIR);

                    $psnGameCount = $user->trophyTitles()->getIterator()->count();
                    $scannedGames = array();

                    // Look through each and every game
                    foreach ($user->trophyTitles() as $trophyTitle) {
                        $npid = $trophyTitle->npCommunicationId();
                        array_push($scannedGames, $npid);
                        $newTrophies = false;

                        $sonyLastUpdatedDate = date_create($trophyTitle->lastUpdatedDateTime());
                        // Does this user already have the game?
                        if (isset($gameLastUpdatedDate[$npid])) {
                            $dbLastUpdatedDate = date_create($gameLastUpdatedDate[$npid]);

                            // Is the timestamp for this game the same as before?
                            if ($sonyLastUpdatedDate == $dbLastUpdatedDate) {
                                $isReturningPlayer = $playerData["last_updated_date"] !== null;
                                $noHiddenTrophies = $playerData["trophy_count_npwr"] == $playerData["trophy_count_sony"];

                                if ($isReturningPlayer && $noHiddenTrophies) {
                                    // Check if game count is the same
                                    $stmt = $this->database->prepare("
                                        SELECT COUNT(ttp.np_communication_id)
                                        FROM trophy_title_player ttp
                                        WHERE ttp.account_id = :account_id
                                        AND ttp.np_communication_id LIKE 'N%'
                                    ");
                                    $stmt->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                                    $stmt->execute();
                                    $ourGameCount = (int)$stmt->fetchColumn();

                                    if ($ourGameCount === $psnGameCount) {
                                        $playerLastUpdated = date_create($playerData["last_updated_date"]);

                                        // Check if we have passed player's last update date
                                        if ($dbLastUpdatedDate < $playerLastUpdated) {
                                            // Assume we are done, and break out of the scan loop
                                            break;
                                        }
                                    }
                                }

                                // Game seems scanned already, skip to next.
                                continue;
                            }
                        }

                        // Add trophy title (game) information into database
                        $query = $this->database->prepare("SELECT set_version
                            FROM   trophy_title
                            WHERE  np_communication_id = :np_communication_id ");
                        $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                        $query->execute();
                        $setVersion = $query->fetchColumn();
                        if (!$setVersion || $setVersion != $trophyTitle->trophySetVersion()) {
                            $trophyTitleIconFilename = $this->downloadMandatoryImage(
                                $trophyTitle->iconUrl(),
                                self::TITLE_ICON_DIRECTORY,
                                sprintf('title icon for "%s" (%s)', $trophyTitle->name(), $npid)
                            );

                            $query = $this->database->prepare("INSERT INTO trophy_title(
                                    np_communication_id,
                                    name,
                                    detail,
                                    icon_url,
                                    platform,
                                    message,
                                    set_version
                                )
                                VALUES(
                                    :np_communication_id,
                                    :name,
                                    :detail,
                                    :icon_url,
                                    :platform,
                                    '',
                                    ''
                                ) AS new
                                ON DUPLICATE KEY
                                UPDATE
                                    icon_url = new.icon_url,
                                    platform = new.platform");
                            $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                            $query->bindValue(
                                ":name",
                                $this->convertToApaTitleCase($this->sanitizeTrophyTitleName($trophyTitle->name())),
                                PDO::PARAM_STR
                            );
                            $query->bindValue(":detail", $trophyTitle->detail(), PDO::PARAM_STR);
                            $query->bindValue(":icon_url", $trophyTitleIconFilename, PDO::PARAM_STR);
                            $platforms = "";
                            foreach ($trophyTitle->platform() as $platform) {
                                $platformValue = $platform->value;
                                if ($platformValue === 'PSPC') {
                                    $platformValue = 'PC';
                                }

                                $platforms .= $platformValue .",";
                            }
                            $platforms = rtrim($platforms, ",");
                            $query->bindValue(":platform", $platforms, PDO::PARAM_STR);
                            // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                            $query->execute();
                        }

                        // Get "groups" (game and DLCs)
                        foreach ($client->trophies($npid, $trophyTitle->serviceName())->trophyGroups() as $trophyGroup) {
                            // Add trophy group (game + dlcs) into database
                            $query = $this->database->prepare("SELECT Count(*)
                                FROM   trophy_group
                                WHERE  np_communication_id = :np_communication_id
                                    AND group_id = :group_id ");
                            $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                            $query->bindValue(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                            $query->execute();
                            $check = $query->fetchColumn();
                            if ($check == 0 || $setVersion != $trophyTitle->trophySetVersion()) {
                                $trophyGroupIconFilename = $this->downloadMandatoryImage(
                                    $trophyGroup->iconUrl(),
                                    self::GROUP_ICON_DIRECTORY,
                                    sprintf('trophy group icon for "%s" (%s/%s)', $trophyGroup->name(), $npid, $trophyGroup->id())
                                );

                                $query = $this->database->prepare("INSERT INTO trophy_group(
                                        np_communication_id,
                                        group_id,
                                        name,
                                        detail,
                                        icon_url
                                    )
                                    VALUES(
                                        :np_communication_id,
                                        :group_id,
                                        :name,
                                        :detail,
                                        :icon_url
                                    ) AS new
                                    ON DUPLICATE KEY
                                    UPDATE
                                        detail = new.detail,
                                        icon_url = new.icon_url");
                                $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                                $query->bindValue(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                                $query->bindValue(":name", $trophyGroup->name(), PDO::PARAM_STR);
                                $query->bindValue(":detail", $trophyGroup->detail(), PDO::PARAM_STR);
                                $query->bindValue(":icon_url", $trophyGroupIconFilename, PDO::PARAM_STR);
                                // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                                $query->execute();

                                // Add trophies into database
                                foreach ($trophyGroup->trophies() as $trophy) {
                                    $query = $this->database->prepare("SELECT Count(*)
                                        FROM   trophy
                                        WHERE  np_communication_id = :np_communication_id
                                            AND group_id = :group_id
                                            AND order_id = :order_id ");
                                    $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                                    $query->bindValue(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                                    $query->bindValue(":order_id", $trophy->id(), PDO::PARAM_INT);
                                    $query->execute();
                                    $check = $query->fetchColumn();
                                    if ($check == 0 || $setVersion != $trophyTitle->trophySetVersion()) {
                                        $trophyIconFilename = $this->downloadMandatoryImage(
                                            $trophy->iconUrl(),
                                            self::TROPHY_ICON_DIRECTORY,
                                            sprintf(
                                                'trophy icon for "%s" (%s/%s/%d)',
                                                $trophy->name(),
                                                $npid,
                                                $trophyGroup->id(),
                                                $trophy->id()
                                            )
                                        );

                                        $rewardImageFilename = $this->downloadOptionalImage(
                                            $trophy->rewardImageUrl(),
                                            self::REWARD_ICON_DIRECTORY,
                                            sprintf(
                                                'reward image for "%s" (%s/%s/%d)',
                                                $trophy->name(),
                                                $npid,
                                                $trophyGroup->id(),
                                                $trophy->id()
                                            )
                                        );

                                        $query = $this->database->prepare("INSERT INTO trophy(
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
                                            VALUES(
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
                                            ON DUPLICATE KEY
                                            UPDATE
                                                hidden = new.hidden,
                                                type = new.type,
                                                name = new.name,
                                                detail = new.detail,
                                                icon_url = new.icon_url,
                                                progress_target_value = new.progress_target_value,
                                                reward_name = new.reward_name,
                                                reward_image_url = new.reward_image_url");
                                        $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                                        $query->bindValue(":group_id", $trophyGroup->id(), PDO::PARAM_STR);
                                        $query->bindValue(":order_id", $trophy->id(), PDO::PARAM_INT);
                                        $query->bindValue(":hidden", $trophy->hidden(), PDO::PARAM_INT);
                                        $trophyTypeEnumValue = $trophy->type()->value;
                                        $query->bindValue(":type", $trophyTypeEnumValue, PDO::PARAM_STR);
                                        $query->bindValue(":name", $trophy->name(), PDO::PARAM_STR);
                                        $query->bindValue(":detail", $trophy->detail(), PDO::PARAM_STR);
                                        $query->bindValue(":icon_url", $trophyIconFilename, PDO::PARAM_STR);
                                        if ($trophy->progressTargetValue() === '') {
                                            $progressTargetValue = null;
                                        } else {
                                            $progressTargetValue = $trophy->progressTargetValue();
                                        }
                                        $query->bindValue(":progress_target_value", $progressTargetValue, PDO::PARAM_INT);
                                        if ($trophy->rewardName() === '') {
                                            $rewardName = null;
                                        } else {
                                            $rewardName = $trophy->rewardName();
                                        }
                                        $query->bindValue(":reward_name", $rewardName, PDO::PARAM_STR);
                                        $query->bindValue(":reward_image_url", $rewardImageFilename, PDO::PARAM_STR);
                                        // Don't insert platinum/gold/silver/bronze here since our site recalculate this.
                                        $query->execute();

                                        $newTrophies = true;
                                    }
                                }

                                if ($newTrophies) {
                                    $query = $this->database->prepare("SELECT status
                                        FROM   trophy_title
                                        WHERE  np_communication_id = :np_communication_id ");
                                    $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                                    $query->execute();
                                    $status = $query->fetchColumn();
                                    if ($status == 2) { // A "Merge Title" have gotten new trophies. Add a log about it so admin can check it out later and fix this.
                                        $this->logger->log("New trophies added for ". $trophyTitle->name() .". ". $npid . ", ". $trophyGroup->id() .", ". $trophyGroup->name());
                                    } else {
                                        $this->logger->log("SET VERSION for ". $trophyTitle->name() .". ". $npid . ", ". $trophyGroup->id() .", ". $trophyGroup->name());
                                    }


                                }
                            }
                        }

                        if ($newTrophies) {
                            $query = $this->database->prepare("SELECT id
                                FROM   trophy_title
                                WHERE  np_communication_id = :np_communication_id ");
                            $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                            $query->execute();
                            $id = $query->fetchColumn();

                            $query = $this->database->prepare("INSERT INTO `psn100_change` (`change_type`, `param_1`) VALUES ('GAME_VERSION', :param_1)");
                            $query->bindValue(":param_1", $id, PDO::PARAM_INT);
                            $query->execute();
                        }

                        // Successfully went through title, groups and trophies. Set the version.
                        if (!$setVersion || $setVersion != $trophyTitle->trophySetVersion()) {
                            $query = $this->database->prepare("UPDATE
                                    trophy_title
                                SET
                                    set_version = :set_version
                                WHERE
                                    np_communication_id = :np_communication_id");
                            $query->bindValue(":set_version", $trophyTitle->trophySetVersion(), PDO::PARAM_STR);
                            $query->bindValue(":np_communication_id", $npid, PDO::PARAM_STR);
                            $query->execute();
                        }

                        // Fetch user trophies
                        foreach ($trophyTitle->trophyGroups() as $trophyGroup) {
                            foreach ($trophyGroup->trophies() as $trophy) {
                                $trophyEarned = $trophy->earned();
                                $progress = (clone $trophy)->progress();
                                if ($trophyEarned || ($progress != '' && intval($progress) > 0)) {
                                    if ($trophy->earnedDateTime() === '') {
                                        $dtAsTextForInsert = null;
                                    } else {
                                        $dateTimeObject = DateTime::createFromFormat("Y-m-d\TH:i:s\Z", $trophy->earnedDateTime());
                                        $dtAsTextForInsert = $dateTimeObject->format("Y-m-d H:i:s");
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

                    if ($psnGameCount != $ourGameCount) {
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
                    // Update user rarity points for each game
                    $query = $this->database->prepare("WITH
                            rarity AS(
                            SELECT
                                trophy_earned.np_communication_id,
                                SUM(trophy.rarity_point) AS points,
                                SUM(trophy.rarity_name = 'COMMON') common,
                                SUM(trophy.rarity_name = 'UNCOMMON') uncommon,
                                SUM(trophy.rarity_name = 'RARE') rare,
                                SUM(trophy.rarity_name = 'EPIC') epic,
                                SUM(trophy.rarity_name = 'LEGENDARY') legendary
                            FROM
                                trophy_earned
                            JOIN trophy USING(
                                    np_communication_id,
                                    order_id
                                )
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
                            ttp.common = rarity.common,
                            ttp.uncommon = rarity.uncommon,
                            ttp.rare = rarity.rare,
                            ttp.epic = rarity.epic,
                            ttp.legendary = rarity.legendary
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
                                IFNULL(SUM(legendary), 0) AS legendary
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
                            p.legendary = rarity.legendary
                        WHERE
                            p.account_id = :account_id AND p.status = 0");
                    $query->bindValue(":account_id", $user->accountId(), PDO::PARAM_INT);
                    $query->execute();
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
            }
        }
    }

    private function downloadMandatoryImage(string $url, string $directory, string $description): string
    {
        $contents = $this->fetchRemoteFile($url);
        if ($contents === null) {
            $this->logger->log(sprintf('Unable to download %s from "%s".', $description, $url));

            return '.png';
        }

        $storedFilename = $this->storeImageContents($url, $directory, $description, $contents);

        return $storedFilename ?? '.png';
    }

    private function downloadOptionalImage(?string $url, string $directory, string $description): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $contents = $this->fetchRemoteFile($url);
        if ($contents === null) {
            $this->logger->log(sprintf('Unable to download %s from "%s".', $description, $url));

            return '.png';
        }

        $storedFilename = $this->storeImageContents($url, $directory, $description, $contents);

        return $storedFilename ?? '.png';
    }

    private function storeImageContents(string $url, string $directory, string $description, string $contents): ?string
    {
        $filename = $this->buildFilename($url, $contents);
        $path = $directory . $filename;

        if (!file_exists($path)) {
            if (@file_put_contents($path, $contents) === false) {
                $this->logger->log(sprintf('Unable to save %s from "%s" to "%s".', $description, $url, $path));

                return null;
            }
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

    private function sanitizeTrophyTitleName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            return $name;
        }

        $name = str_replace(['™', '®', '©'], '', $name);

        // Normalize en dash to hyphen-minus to keep downstream handling consistent.
        $name = str_replace('–', '-', $name);

        if ($name === '') {
            return $name;
        }

        $prefixPatterns = [
            '/^Trophy Set For\b[:\s-]*/i',
            '/^Trophy Set\b[:\s-]*/i',
            '/^Trophyset\b[:\s-]*/i',
        ];

        foreach ($prefixPatterns as $pattern) {
            $name = preg_replace($pattern, '', $name, 1);
        }

        $suffixPatterns = [
            '/\s*Trophy Set\.$/i',
            '/\s*Trophy Set$/i',
            '/\s*Trophyset\.$/i',
            '/\s*Trophyset$/i',
            '/\s*Trophies$/i',
            '/\s*Trophy$/i',
        ];

        foreach ($suffixPatterns as $pattern) {
            if (preg_match($pattern, $name)) {
                $name = preg_replace($pattern, '', $name);
                break;
            }
        }

        if (substr($name, -2) === ' -') {
            $name = rtrim(substr($name, 0, -2));
        }

        $separatorPosition = strpos($name, ' - ');

        if ($separatorPosition !== false) {
            $prefix = substr($name, 0, $separatorPosition);

            if (strpos($prefix, ':') === false) {
                $name = substr_replace($name, ': ', $separatorPosition, 3);
            }
        }

        $name = rtrim($name);

        if ($name !== '') {
            $name = rtrim($name, '.');
        }

        return trim($name);
    }

    private function convertToApaTitleCase(string $title): string
    {
        $title = trim($title);

        if ($title === '') {
            return $title;
        }

        $lowercaseWords = [
            // Articles
            'a',
            'an',
            'the',
            // Coordinating conjunctions
            'and',
            'but',
            'or',
            'nor',
            'for',
            'so',
            'yet',
            // Short prepositions (three letters or fewer)
            'as',
            'at',
            'by',
            'in',
            'of',
            'on',
            'per',
            'to',
            'via',
            'up',
            'off',
            'out',
            // Other permitted lowercase forms
            'vs',
            'vs.',
        ];

        $words = preg_split('/\s+/u', $title);

        if ($words === false) {
            return $title;
        }

        $wordCount = count($words);
        $convertedWords = [];
        $capitalizeNext = false;

        for ($index = 0; $index < $wordCount; $index++) {
            $word = $words[$index];

            if ($word === '') {
                $convertedWords[] = '';
                continue;
            }

            $leadingPunctuation = '';
            $trailingPunctuation = '';
            $coreWord = $word;

            if (preg_match('/^([\\"\'"“‘\(\[{]*)(.*?)([\\"\'"”’\)\]}.,:;!?]*)$/u', $word, $matches) === 1) {
                $leadingPunctuation = $matches[1];
                $coreWord = $matches[2];
                $trailingPunctuation = $matches[3];
            }

            if ($coreWord === '') {
                $convertedWords[] = $word;
                $capitalizeNext = $this->shouldCapitalizeAfterPunctuation($word);
                continue;
            }

            $isFirstWord = $index === 0;
            $isLastWord = $index === $wordCount - 1;
            $forceCapitalize = $capitalizeNext || $isFirstWord || $isLastWord;

            $processedCore = $this->formatTitleWord($coreWord, $forceCapitalize, $lowercaseWords);

            $convertedWords[] = $leadingPunctuation . $processedCore . $trailingPunctuation;

            $capitalizeNext = $this->shouldCapitalizeAfterPunctuation($trailingPunctuation);
        }

        return implode(' ', $convertedWords);
    }

    private function formatTitleWord(string $word, bool $forceCapitalize, array $lowercaseWords): string
    {
        if (str_contains($word, '-')) {
            $segments = explode('-', $word);

            foreach ($segments as $key => $segment) {
                $segments[$key] = $this->formatTitleWord($segment, true, $lowercaseWords);
            }

            return implode('-', $segments);
        }

        $wordLower = mb_strtolower($word, 'UTF-8');

        if (!$forceCapitalize && in_array($wordLower, $lowercaseWords, true)) {
            return $wordLower;
        }

        if ($this->shouldPreserveTitleWord($word)) {
            return $word;
        }

        return $this->uppercaseFirstCharacter($wordLower);
    }

    private function shouldPreserveTitleWord(string $word): bool
    {
        if ($word === '') {
            return true;
        }

        $acronyms = [
            'VR',
            'HD',
        ];

        if (in_array($word, $acronyms, true)) {
            return true;
        }

        if (preg_match('/\d/', $word) === 1) {
            return true;
        }

        if (str_contains($word, '.')) {
            return true;
        }

        if (str_contains($word, '&')) {
            return true;
        }

        $romanNumeral = mb_strtoupper($word, 'UTF-8');

        if (preg_match('/^(?=[IVXLCDM]+$)M{0,4}(CM|CD|D?C{0,3})(XC|XL|L?X{0,3})(IX|IV|V?I{0,3})$/', $romanNumeral) === 1) {
            return true;
        }

        $lower = mb_strtolower($word, 'UTF-8');
        $upper = mb_strtoupper($word, 'UTF-8');

        return $word !== $lower && $word !== $upper;
    }

    private function uppercaseFirstCharacter(string $word): string
    {
        return mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
    }

    private function shouldCapitalizeAfterPunctuation(string $punctuation): bool
    {
        if ($punctuation === '') {
            return false;
        }

        $triggerCharacters = [':', '!', '?', '.'];

        foreach ($triggerCharacters as $character) {
            if (str_contains($punctuation, $character)) {
                return true;
            }
        }

        return false;
    }
}
