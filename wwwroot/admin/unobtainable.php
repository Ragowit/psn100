<?php
ini_set("max_execution_time", "0");
ini_set("mysql.connect_timeout", "0");
ini_set("default_socket_timeout", "6000");
set_time_limit(0);
require_once("../init.php");

if (isset($_POST["trophy"])) {
    $trophyId = $_POST["trophy"];
    $status = $_POST["status"];
    $trophies = explode(",", $trophyId);

    $trophyNames = array();
    $trophyGroups = array();
    $trophyTitles = array();
    foreach ($trophies as $trophyId) {
        $database->beginTransaction();
        $query = $database->prepare("UPDATE trophy SET status = :status WHERE id = :trophy_id");
        $query->bindParam(":status", $status, PDO::PARAM_INT);
        $query->bindParam(":trophy_id", $trophyId, PDO::PARAM_INT);
        $query->execute();
        $database->commit();

        $query = $database->prepare("SELECT np_communication_id, group_id, name FROM trophy WHERE id = :trophy_id");
        $query->bindParam(":trophy_id", $trophyId, PDO::PARAM_INT);
        $query->execute();
        $trophy = $query->fetch();
        array_push($trophyNames, $trophyId ." (". $trophy["name"] .")");
        array_push($trophyGroups, $trophy["np_communication_id"].",".$trophy["group_id"]);
        array_push($trophyTitles, $trophy["np_communication_id"]);
    }
    $trophyGroups = array_unique($trophyGroups);
    $trophyTitles = array_unique($trophyTitles);

    // Recalculate trophies for trophy group
    foreach ($trophyGroups as $trophyGroup) {
        $explode = explode(",", $trophyGroup);
        $trophy["np_communication_id"] = $explode[0];
        $trophy["group_id"] = $explode[1];
        
        // The new trophy count in trophy group
        $query = $database->prepare("WITH bronze AS (
            SELECT
              COUNT(*) AS count
            FROM
              trophy
            WHERE
              np_communication_id = :np_communication_id
              AND group_id = :group_id
              AND status = 0
              AND type = 'bronze'
          ),
          silver AS (
            SELECT
              COUNT(*) AS count
            FROM
              trophy
            WHERE
              np_communication_id = :np_communication_id
              AND group_id = :group_id
              AND status = 0
              AND type = 'silver'
          ),
          gold AS (
            SELECT
              COUNT(*) AS count
            FROM
              trophy
            WHERE
              np_communication_id = :np_communication_id
              AND group_id = :group_id
              AND status = 0
              AND type = 'gold'
          ),
          platinum AS (
            SELECT
              COUNT(*) AS count
            FROM
              trophy
            WHERE
              np_communication_id = :np_communication_id
              AND group_id = :group_id
              AND status = 0
              AND type = 'platinum'
          )
          UPDATE
            trophy_group tg,
            bronze b,
            silver s,
            gold g,
            platinum p
          SET
            tg.bronze = b.count,
            tg.silver = s.count,
            tg.gold = g.count,
            tg.platinum = p.count
          WHERE
            tg.np_communication_id = :np_communication_id
            AND tg.group_id = :group_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();

        // Recalculate stats for trophy group for all the affected players
        // bronze
        $query = $database->prepare("WITH player_trophy_count AS(
            SELECT
                account_id,
                COUNT(type) AS count
            FROM
                trophy_earned te
                LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
                AND t.group_id = te.group_id
                AND t.order_id = te.order_id
                AND t.status = 0
                AND t.type = 'bronze'
            WHERE
                te.np_communication_id = :np_communication_id
                AND te.group_id = :group_id
            GROUP BY
                account_id
            )
            UPDATE
                trophy_group_player tgp,
                player_trophy_count ptc
            SET
                tgp.bronze = ptc.count
            WHERE
                tgp.np_communication_id = :np_communication_id
                AND tgp.group_id = :group_id
                AND tgp.account_id = ptc.account_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();

        // silver
        $query = $database->prepare("WITH player_trophy_count AS(
            SELECT
                account_id,
                COUNT(type) AS count
            FROM
                trophy_earned te
                LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
                AND t.group_id = te.group_id
                AND t.order_id = te.order_id
                AND t.status = 0
                AND t.type = 'silver'
            WHERE
                te.np_communication_id = :np_communication_id
                AND te.group_id = :group_id
            GROUP BY
                account_id
            )
            UPDATE
                trophy_group_player tgp,
                player_trophy_count ptc
            SET
                tgp.silver = ptc.count
            WHERE
                tgp.np_communication_id = :np_communication_id
                AND tgp.group_id = :group_id
                AND tgp.account_id = ptc.account_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();

        // gold
        $query = $database->prepare("WITH player_trophy_count AS(
            SELECT
                account_id,
                COUNT(type) AS count
            FROM
                trophy_earned te
                LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
                AND t.group_id = te.group_id
                AND t.order_id = te.order_id
                AND t.status = 0
                AND t.type = 'gold'
            WHERE
                te.np_communication_id = :np_communication_id
                AND te.group_id = :group_id
            GROUP BY
                account_id
            )
            UPDATE
                trophy_group_player tgp,
                player_trophy_count ptc
            SET
                tgp.gold = ptc.count
            WHERE
                tgp.np_communication_id = :np_communication_id
                AND tgp.group_id = :group_id
                AND tgp.account_id = ptc.account_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();

        // platinum
        $query = $database->prepare("WITH player_trophy_count AS(
            SELECT
                account_id,
                COUNT(type) AS count
            FROM
                trophy_earned te
                LEFT JOIN trophy t ON t.np_communication_id = te.np_communication_id
                AND t.group_id = te.group_id
                AND t.order_id = te.order_id
                AND t.status = 0
                AND t.type = 'platinum'
            WHERE
                te.np_communication_id = :np_communication_id
                AND te.group_id = :group_id
            GROUP BY
                account_id
            )
            UPDATE
                trophy_group_player tgp,
                player_trophy_count ptc
            SET
                tgp.platinum = ptc.count
            WHERE
                tgp.np_communication_id = :np_communication_id
                AND tgp.group_id = :group_id
                AND tgp.account_id = ptc.account_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();

        // progresss
        $query = $database->prepare("WITH max_score AS (
            SELECT
                bronze * 15 + silver * 30 + gold * 90 AS points
            FROM
                trophy_group
            WHERE
                np_communication_id = :np_communication_id
                AND group_id = :group_id
            ),
            user_score AS (
                SELECT
                    account_id,
                    bronze * 15 + silver * 30 + gold * 90 AS points
                FROM
                    trophy_group_player
                WHERE
                    np_communication_id = :np_communication_id
                    AND group_id = :group_id
                GROUP BY
                    account_id
            )
            UPDATE
                trophy_group_player tgp,
                max_score ms,
                user_score us
            SET
                tgp.progress = IF(
                    ms.points = 0,
                    100,
                    IF(
                        us.points != 0
                        AND FLOOR(us.points / ms.points * 100) = 0,
                        1,
                        FLOOR(us.points / ms.points * 100)
                    )
                )
            WHERE
                tgp.np_communication_id = :np_communication_id
                AND tgp.group_id = :group_id
                AND tgp.account_id = us.account_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":group_id", $trophy["group_id"], PDO::PARAM_STR);
        $query->execute();
    }

    // Recalculate trophies for trophy title
    foreach ($trophyTitles as $trophyTitle) {
        $trophy["np_communication_id"] = $trophyTitle;
        
        $query = $database->prepare("WITH trophy_group_count AS (
            SELECT
              SUM(bronze) AS bronze,
              SUM(silver) AS silver,
              SUM(gold) AS gold,
              SUM(platinum) AS platinum
            FROM
              trophy_group
            WHERE
              np_communication_id = :np_communication_id
          )
          UPDATE
            trophy_title tt,
            trophy_group_count tgc
          SET
            tt.bronze = tgc.bronze,
            tt.silver = tgc.silver,
            tt.gold = tgc.gold,
            tt.platinum = tgc.platinum
          WHERE
            np_communication_id = :np_communication_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->execute();

        // Recalculate stats for trophy title for the affected players
        $query = $database->prepare("WITH player_trophy_count AS (
            SELECT
                account_id,
                IFNULL(SUM(bronze), 0) AS bronze,
                IFNULL(SUM(silver), 0) AS silver,
                IFNULL(SUM(gold), 0) AS gold,
                IFNULL(SUM(platinum), 0) AS platinum
            FROM
                trophy_group_player
            WHERE
                np_communication_id = :np_communication_id
            GROUP BY
                account_id
            )
            UPDATE
                trophy_title_player ttp,
                player_trophy_count ptc
            SET
                ttp.bronze = ptc.bronze,
                ttp.silver = ptc.silver,
                ttp.gold = ptc.gold,
                ttp.platinum = ptc.platinum
            WHERE
                ttp.account_id = ptc.account_id
                AND ttp.np_communication_id = :np_communication_id
            ");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->execute();

        // progress
        $query = $database->prepare("SELECT
                bronze * 15 + silver * 30 + gold * 90 AS max_score
            FROM
                trophy_title
            WHERE
                np_communication_id = :np_communication_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->execute();
        $maxScore = $query->fetchColumn();

        $query = $database->prepare("WITH user_score AS (
            SELECT
                account_id,
                bronze * 15 + silver * 30 + gold * 90 AS points
            FROM
                trophy_title_player
            WHERE
                np_communication_id = :np_communication_id
            GROUP BY
                account_id
            )
            UPDATE
                trophy_title_player ttp,
                user_score us
            SET
                ttp.progress = IF(
                    :max_score = 0,
                    100,
                    IF(
                        us.points != 0
                        AND FLOOR(us.points / :max_score * 100) = 0,
                        1,
                        FLOOR(us.points / :max_score * 100)
                    )
                )
            WHERE
                ttp.np_communication_id = :np_communication_id
                AND ttp.account_id = us.account_id");
        $query->bindParam(":np_communication_id", $trophy["np_communication_id"], PDO::PARAM_STR);
        $query->bindParam(":max_score", $maxScore, PDO::PARAM_INT);
        $query->execute();
    }

    if ($status == 1) {
        $statusText = "unobtainable";
    } else {
        $statusText = "obtainable";
    }

    $success = "<p>";
    foreach ($trophyNames as $trophyName) {
        $success .= "Trophy ID ". $trophyName ."<br>";
    }
    $success .= "is now set as ". $statusText ."</p>";
}

?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Unobtainable Trophy</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Trophy ID:<br>
            <input type="text" name="trophy"><br>
            Status:<br>
            <select name="status">
                <option value="1">Unobtainable</option>
                <option value="0">Obtainable</option>
            </select><br><br>
            <input type="submit" value="Submit">
        </form>

        <?php
        if (isset($success)) {
            echo $success;
        }
        ?>
    </body>
</html>
