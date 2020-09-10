<?php
$maintenance = false;
if ($maintenance) {
    require_once("maintenance.php");
    die();
}

require_once("init.php");

if (!isset($_COOKIE["seen_cookie"])) {
    $showCookie = true;
}
setcookie("seen_cookie", "true", time() + (86400 * 30), "/"); // 86400 = 1 day

$path = ltrim($_SERVER["SCRIPT_URL"], "/"); // Trim leading slash(es)
$elements = explode("/", $path); // Split path on slashes

if (empty($elements[0])) { // No path elements means home
    require_once("home.php");
} else {
    switch (array_shift($elements)) {
        case "about":
            if (empty($elements[0])) {
                require_once("about.php");
            } else {
                header("Location: /about/", true, 303);
                die();
            }
            break;
        case "avatar":
            if (empty($elements[0])) {
                require_once("avatars.php");
            } else {
                header("Location: /avatar/", true, 303);
                die();
            }
            break;
        case "game":
            if (empty($elements[0])) {
                require_once("games.php");
            } else {
                $gameId = explode("-", array_shift($elements))[0];
                $query = $database->prepare("SELECT id FROM trophy_title WHERE id = :id");
                $query->bindParam(":id", $gameId, PDO::PARAM_INT);
                $query->execute();
                $result = $query->fetchColumn();
                $player = $elements[0];

                if ($result === false) {
                    header("Location: /game/", true, 303);
                    die();
                }

                require_once("game.php");
            }
            break;
        case "game-leaderboard":
            if (empty($elements[0])) {
                header("Location: /game/", true, 303);
                die();
            } else {
                $gameId = explode("-", array_shift($elements))[0];
                $query = $database->prepare("SELECT id FROM trophy_title WHERE id = :id");
                $query->bindParam(":id", $gameId, PDO::PARAM_INT);
                $query->execute();
                $result = $query->fetchColumn();
                $player = $elements[0];

                if ($result === false) {
                    header("Location: /game/", true, 303);
                    die();
                }

                require_once("game_leaderboard.php");
            }
            break;
        case "leaderboard":
            if (empty($elements[0])) {
                header("Location: /leaderboard/main", true, 303);
                die();
            } else {
                switch (array_shift($elements)) {
                    case "main":
                        require_once("leaderboard_main.php");
                        break;
                    case "rarity":
                        require_once("leaderboard_rarity.php");
                        break;
                    default:
                        header("Location: /leaderboard/main", true, 303);
                        die();
                }
            }
            break;
        case "player":
            if (empty($elements[0])) {
                header("Location: /leaderboard/main", true, 303);
                die();
            } else {
                $onlineId = array_shift($elements);
                $query = $database->prepare("SELECT account_id FROM player WHERE online_id = :online_id");
                $query->bindParam(":online_id", $onlineId, PDO::PARAM_STR);
                $query->execute();
                $accountId = $query->fetchColumn();

                if ($accountId === false) {
                    //header('HTTP/1.1 404 Not Found');
                    header("Location: /player/", true, 303);
                    die();
                }

                switch (array_shift($elements)) {
                    case "":
                        require_once("player.php");
                        break;
                    case "advisor":
                        require_once("player_advisor.php");
                        break;
                    case "log":
                        require_once("player_log.php");
                        break;
                    case "timeline":
                        require_once("player_timeline.php");
                        break;
                    default:
                        header("Location: /player/". $onlineId, true, 303);
                        die();
                }
            }
            break;
        case "trophy":
            if (empty($elements[0])) {
                require_once("trophies.php");
            } else {
                $trophyId = explode("-", array_shift($elements))[0];
                $query = $database->prepare("SELECT id FROM trophy WHERE id = :id");
                $query->bindParam(":id", $trophyId, PDO::PARAM_INT);
                $query->execute();
                $result = $query->fetchColumn();
                $player = $elements[0];

                if ($result === false) {
                    header("Location: /trophy/", true, 303);
                    die();
                }

                require_once("trophy.php");
            }
            break;
        default:
            header('HTTP/1.1 404 Not Found');
            require_once("404.php");
            break;
    }
}
