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
        $query = $database->prepare("SELECT p.online_id AS player_name, tt.id AS game_id, tt.name AS game_name FROM player p JOIN trophy_earned te USING (account_id) JOIN trophy_title tt USING (np_communication_id) WHERE (
        (te.np_communication_id = 'NPWR05066_00' AND te.group_id = 'default' AND te.order_id = 2) OR
        (te.np_communication_id = 'NPWR05066_00' AND te.group_id = 'default' AND te.order_id = 9) OR
        (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 19) OR
        (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 20) OR
        (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 21) OR
        (te.np_communication_id = 'NPWR00382_00' AND te.group_id = 'default' AND te.order_id = 22) OR
        (te.np_communication_id = 'NPWR08208_00' AND te.group_id = 'default' AND te.order_id = 0) OR
        (te.np_communication_id = 'NPWR08208_00' AND te.group_id = 'default' AND te.order_id = 10) OR
        (te.np_communication_id = 'NPWR08208_00' AND te.group_id = 'default' AND te.order_id = 12)
        ) AND p.status = 0 GROUP BY player_name ORDER BY player_name");
        $query->execute();
        $possibleCheaters = $query->fetchAll();

        foreach ($possibleCheaters as $possibleCheater) {
            echo "<a href=\"/game/". $possibleCheater["game_id"] ."-". slugify($possibleCheater["game_name"]) ."/". $possibleCheater["player_name"] ."\">". $possibleCheater["player_name"] ."</a><br>";
        }
        ?>
    </body>
</html>
