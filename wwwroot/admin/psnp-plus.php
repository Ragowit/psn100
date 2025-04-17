<?php
require_once("/home/psn100/public_html/init.php");

$json = file_get_contents("https://psnp-plus.netlify.app/list.min.json");
$obj = json_decode($json);
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ PSNP+</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br>

            <h1>PSNP+ changes</h1>
            <?
            $games = array();
            // PSNProfiles have some unreleased games that PSN100 doesn't have.
            $unreleasedGames = array(2409, 2410, 2414, 2552, 4234, 4236, 4237, 4240, 4241, 5012, 5318, 5925, 6420, 7082, 7272, 8352, 8644, 8672,
                10225, 10314, 10519, 10601, 10834, 11153, 12139, 13787, 13788, 14299, 16636, 16827, 16907, 16935, 17071, 17166, 17244, 17359, 17420, 17575, 17745, 18171, 18552, 18895,
                19215, 19384, 19652, 20031, 21204, 21794, 25746, 26964, 27034, 27039, 27040, 27294, 27804, 27979, 28410, 29376, 29729, 29730, 32645, 33091);

            foreach ($obj->list as $psnprofiles_id => $trophies) {
                // Skip unreleased games that PSN100 doesn't have anyway.
                if (in_array($psnprofiles_id, $unreleasedGames)) {
                    continue;
                }

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

                        echo "<br>";
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
        </div>
    </body>
</html>
