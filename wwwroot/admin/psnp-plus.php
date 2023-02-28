<?php
require_once("/home/psn100/public_html/init.php");

$json = file_get_contents("https://psnp-plus.netlify.app/list.min.json");
$obj = json_decode($json);
?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ PSNP+</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br>

        <h1>PSNP+ changes</h1>
        <?
        $games = array();

        foreach ($obj->list as $psnprofiles_id => $trophies) {
            $query = $database->prepare("SELECT
                    id, np_communication_id, `name`
                FROM
                    trophy_title
                WHERE
                    psnprofiles_id = :psnprofiles_id");
            $query->bindParam(":psnprofiles_id", $psnprofiles_id, PDO::PARAM_INT);
            $query->execute();
            $game = $query->fetch();

            if (!$game) {
                echo "PSNProfiles ID <a href='https://psnprofiles.com/trophies/". $psnprofiles_id ."' target='_blank'>". $psnprofiles_id ."</a> not in our database.<br>";
            } else {
                array_push($games, $game["np_communication_id"]);

                // PSNProfiles order_id starts at 1 while we/sony start at 0
                $query = $database->prepare("SELECT
                        order_id + 1
                    FROM
                        trophy
                    WHERE
                        np_communication_id = :np_communication_id AND `status` = 1");
                $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                $query->execute();
                $ourTrophies = $query->fetchAll(PDO::FETCH_COLUMN);

                if ($trophies[0] == 0) { // All trophies
                    $query = $database->prepare("SELECT
                        order_id + 1
                    FROM
                        trophy
                    WHERE
                        np_communication_id = :np_communication_id");
                    $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                    $query->execute();
                    $trophies = $query->fetchAll(PDO::FETCH_COLUMN);
                }

                $unobtainable = array_diff($trophies, $ourTrophies);
                $obtainable = array_diff($ourTrophies, $trophies);

                if (count($unobtainable) > 0 || count($obtainable) > 0) {
                    echo "<b><a href='../game/". $game["id"] ."' target='_blank'>". $game["name"] ."</a></b><br>";

                    if (count($unobtainable) > 0) {
                        $unobtainableData = array();
                        foreach ($unobtainable as $trophy) {
                            $orderId = $trophy - 1;
                            $query = $database->prepare("SELECT
                                    id
                                FROM
                                    trophy
                                WHERE
                                    np_communication_id = :np_communication_id AND order_id = :order_id");
                            $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                            $query->bindParam(":order_id", $orderId, PDO::PARAM_INT);
                            $query->execute();
                            $trophyId = $query->fetchColumn();

                            array_push($unobtainableData, $trophyId);
                        }

                        echo "<a href='unobtainable.php?status=1&trophy=". implode(",", $unobtainableData) ."'>Unobtainable</a>: ";
                        echo implode(", ", $unobtainable);
                        echo "<br>";
                    }

                    if (count($obtainable) > 0) {
                        $obtainableData = array();
                        foreach ($obtainable as $trophy) {
                            $orderId = $trophy - 1;
                            $query = $database->prepare("SELECT
                                    id
                                FROM
                                    trophy
                                WHERE
                                    np_communication_id = :np_communication_id AND order_id = :order_id");
                            $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                            $query->bindParam(":order_id", $orderId, PDO::PARAM_INT);
                            $query->execute();
                            $trophyId = $query->fetchColumn();

                            array_push($obtainableData, $trophyId);
                        }

                        echo "<a href='unobtainable.php?status=0&trophy=". implode(",", $obtainableData) ."'>Obtainable</a>: ";
                        echo implode(", ", $obtainable);
                        echo "<br>";
                    }

                    $gameLink = $game["id"] ."-". slugify($game["name"]);
                    echo "<textarea id='github-". $game["id"] ."' hidden>";
                    echo "**Game**\r\n- https://psn100.net/game/". $gameLink ."\r\n\r\n**Trophy**";
                    foreach ($trophies as $trophy) {
                        $orderId = $trophy - 1;
                        $query = $database->prepare("SELECT
                                id, `name`
                            FROM
                                trophy
                            WHERE
                                np_communication_id = :np_communication_id AND order_id = :order_id");
                        $query->bindParam(":np_communication_id", $game["np_communication_id"], PDO::PARAM_STR);
                        $query->bindParam(":order_id", $orderId, PDO::PARAM_INT);
                        $query->execute();
                        $trophyData = $query->fetch();

                        $trophyLink = $trophyData["id"] ."-". slugify($trophyData["name"]);
                        echo "\r\n- https://psn100.net/trophy/". $trophyLink;
                    }
                    echo "\r\n\r\n**Source**\r\n- https://psnp-plus.netlify.app/";
                    echo "</textarea>";
                    echo "<button onclick='myFunction(". $game["id"] .")'>Copy to clipboard for GitHub</button>";
                    echo "<br><br>";
                }
            }
        }
        ?>

        <h1>No longer in PSNP+ (all trophies fixed!)</h1>
        <?php
        $query = $database->prepare("SELECT DISTINCT np_communication_id FROM trophy WHERE `status` = 1 AND np_communication_id LIKE 'N%'");
        $query->execute();
        $ourGames = $query->fetchAll(PDO::FETCH_COLUMN);

        $fixedGames = array_diff($ourGames, $games);
        foreach ($fixedGames as $game) {
            $query = $database->prepare("SELECT
                    id, `name`
                FROM
                    trophy_title
                WHERE
                    np_communication_id = :np_communication_id");
            $query->bindParam(":np_communication_id", $game, PDO::PARAM_STR);
            $query->execute();
            $trophyTitle = $query->fetch();
            echo "<b><a href='../game/". $trophyTitle["id"] ."' target='_blank'>". $trophyTitle["name"] ."</a></b><br>";

            $query = $database->prepare("SELECT
                    id
                FROM
                    trophy
                WHERE
                    np_communication_id = :np_communication_id AND `status` = 1");
            $query->bindParam(":np_communication_id", $game, PDO::PARAM_STR);
            $query->execute();
            $obtainableData = $query->fetchAll(PDO::FETCH_COLUMN);
            echo "<a href='unobtainable.php?status=0&trophy=". implode(",", $obtainableData) ."'>Obtainable</a>: ";
            echo implode(", ", $obtainableData);
            echo "<br><br>";
        }
        ?>

        <script>
            function myFunction($id) {
                // Get the text field
                var copyText = document.getElementById("github-" + $id);

                // Select the text field
                copyText.select();
                copyText.setSelectionRange(0, 99999); // For mobile devices

                // Copy the text inside the text field
                navigator.clipboard.writeText(copyText.value);
                
                // Alert the copied text
                alert("Copied the text: " + copyText.value);
            }
        </script>
    </body>
</html>
