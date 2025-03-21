<?php
$maintenance = false;
if ($maintenance) {
    require_once("maintenance.php");
    die();
}

require_once("init.php");

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
        case "changelog":
            if (empty($elements[0])) {
                require_once("changelog.php");
            } else {
                header("Location: /changelog/", true, 303);
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
                $player = array_key_exists(0, $elements) ? $elements[0] : null;

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
                $player = array_key_exists(0, $elements) ? $elements[0] : null;

                if ($result === false) {
                    header("Location: /game/", true, 303);
                    die();
                }

                require_once("game_leaderboard.php");
            }
            break;
        case "game-recent-players":
            if (empty($elements[0])) {
                header("Location: /game/", true, 303);
                die();
            } else {
                $gameId = explode("-", array_shift($elements))[0];
                $query = $database->prepare("SELECT id FROM trophy_title WHERE id = :id");
                $query->bindParam(":id", $gameId, PDO::PARAM_INT);
                $query->execute();
                $result = $query->fetchColumn();
                $player = array_key_exists(0, $elements) ? $elements[0] : null;

                if ($result === false) {
                    header("Location: /game/", true, 303);
                    die();
                }

                require_once("game_recent_players.php");
            }
            break;
        case "leaderboard":
            if (empty($elements[0])) {
                header("Location: /leaderboard/trophy", true, 303);
                die();
            } else {
                switch (array_shift($elements)) {
                    case "main":
                    case "trophy":
                        require_once("leaderboard_main.php");
                        break;
                    case "rarity":
                        require_once("leaderboard_rarity.php");
                        break;
                    default:
                        header("Location: /leaderboard/trophy", true, 303);
                        die();
                }
            }
            break;
        case "player":
            if (empty($elements[0])) {
                header("Location: /leaderboard/trophy", true, 303);
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

                $query = $database->prepare("SELECT country FROM player WHERE account_id = :account_id");
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();
                $playerCountry = $query->fetchColumn();

                $query = $database->prepare("SELECT
                        p.*, r1.ranking, r1.rarity_ranking, r2.ranking_country, r2.rarity_ranking_country
                    FROM player p
                    LEFT JOIN (SELECT account_id, RANK() OVER (ORDER BY `points` DESC, `platinum` DESC, `gold` DESC, `silver` DESC) `ranking`, RANK() OVER (ORDER BY `rarity_points` DESC) `rarity_ranking` FROM `player` WHERE `status` = 0) r1 ON p.account_id = r1.account_id
                    LEFT JOIN (SELECT account_id, RANK() OVER (ORDER BY `points` DESC, `platinum` DESC, `gold` DESC, `silver` DESC) `ranking_country`, RANK() OVER (ORDER BY `rarity_points` DESC) `rarity_ranking_country` FROM `player` WHERE `status` = 0 AND `country` = :country) r2 ON p.account_id = r2.account_id
                    WHERE p.account_id = :account_id");
                $query->bindParam(":country", $playerCountry, PDO::PARAM_STR);
                $query->bindParam(":account_id", $accountId, PDO::PARAM_INT);
                $query->execute();
                $player = $query->fetch();

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
                    case "random":
                        require_once("player_random.php");
                        break;
                    case "report":
                        require_once("player_report.php");
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
                $player = array_key_exists(0, $elements) ? $elements[0] : null;

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
