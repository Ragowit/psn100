<?php
require_once("../init.php");
?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Possible Cheaters</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <?php
        $query = $database->prepare("SELECT x.online_id AS player_name, id AS game_id, name AS game_name FROM trophy_title
            JOIN (
                SELECT p.online_id, MIN(tt.np_communication_id) AS np_communication_id FROM trophy_earned te
                JOIN player p USING (account_id)
                JOIN trophy_title tt USING (np_communication_id) WHERE (
                (te.np_communication_id = 'NPWR05066_00' AND te.group_id = 'default' AND te.order_id = 2)
                OR (te.np_communication_id = 'NPWR05066_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 19)
                OR (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 20)
                OR (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 21)
                OR (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR08208_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08208_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR08208_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR03899_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR03899_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR04024_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR04024_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR01472_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR01472_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR03558_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR03558_00' AND te.group_id = 'default' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR05839_00' AND te.group_id = 'default' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR05839_00' AND te.group_id = 'default' AND te.order_id = 28)
                OR (te.np_communication_id = 'NPWR08881_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08881_00' AND te.group_id = 'default' AND te.order_id = 25)
                OR (te.np_communication_id = 'NPWR08881_00' AND te.group_id = 'default' AND te.order_id = 26)
                OR (te.np_communication_id = 'NPWR10400_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR10400_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR01064_00' AND te.group_id = '001' AND te.order_id = 51)
                OR (te.np_communication_id = 'NPWR01064_00' AND te.group_id = '001' AND te.order_id = 57)
                OR (te.np_communication_id = 'NPWR01685_00' AND te.group_id = 'default' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR01685_00' AND te.group_id = '001' AND te.order_id = 15)
                OR (te.np_communication_id = 'NPWR00550_00' AND te.group_id = 'default' AND te.order_id = 4 AND te.earned_date >= '2015-01-01')
                OR (te.np_communication_id = 'NPWR05256_00' AND te.group_id = '001' AND te.order_id = 34)
                OR (te.np_communication_id = 'NPWR05666_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR05666_00' AND te.group_id = 'default' AND te.order_id = 24)
                OR (te.np_communication_id = 'NPWR01264_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR01264_00' AND te.group_id = 'default' AND te.order_id = 44)
                OR (te.np_communication_id = 'NPWR01267_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR01267_00' AND te.group_id = 'default' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR01267_00' AND te.group_id = 'default' AND te.order_id = 31)
                OR (te.np_communication_id = 'NPWR01267_00' AND te.group_id = 'default' AND te.order_id = 32)
                OR (te.np_communication_id = 'NPWR02942_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR02942_00' AND te.group_id = 'default' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 41 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 42 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 43 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 44 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 45 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 46 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 47 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 48 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 49 AND te.earned_date >= '2012-11-15')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.group_id = 'default' AND te.order_id = 50 AND te.earned_date >= '2012-11-15')
                ) AND p.status = 0 GROUP BY online_id) x USING (np_communication_id)
            ORDER BY player_name");
        $query->execute();
        $possibleCheaters = $query->fetchAll();

        foreach ($possibleCheaters as $possibleCheater) {
            echo "<a href=\"/game/". $possibleCheater["game_id"] ."-". slugify($possibleCheater["game_name"]) ."/". $possibleCheater["player_name"] ."\">". $possibleCheater["player_name"] ."</a><br>";
        }
        ?>
    </body>
</html>
