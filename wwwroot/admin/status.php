<?php
require_once("../init.php");
require_once '../classes/GameStatusService.php';

$gameStatusService = new GameStatusService($database);
$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameId = filter_input(INPUT_POST, 'game', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);

    if ($gameId === null || $gameId === false) {
        $error = '<p>Invalid game ID provided.</p>';
    } elseif ($status === null || $status === false) {
        $error = '<p>Invalid status provided.</p>';
    } else {
        try {
            $statusText = $gameStatusService->updateGameStatus((int) $gameId, (int) $status);
            $gameIdText = htmlentities((string) $gameId, ENT_QUOTES, 'UTF-8');
            $statusText = htmlentities($statusText, ENT_QUOTES, 'UTF-8');

            $success = sprintf(
                '<p>Game %s is now set as %s. All affected players will be updated soon, and ranks updated the next whole hour.</p>',
                $gameIdText,
                $statusText
            );
        } catch (InvalidArgumentException $exception) {
            $error = '<p>' . htmlentities($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        } catch (Throwable $exception) {
            $error = '<p>Failed to update game status. Please try again.</p>';
        }
    }
}

?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Game Status</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game ID:<br>
                <input type="number" name="game"><br>
                Status:<br>
                <select name="status">
                    <option value="0">Normal</option>
                    <option value="1">Delisted</option>
                    <option value="3">Obsolete</option>
                    <option value="4">Delisted &amp; Obsolete</option>
                </select><br><br>
                <input type="submit" value="Submit">
            </form>

            <?php
            if ($error !== null) {
                echo $error;
            }

            if ($success !== null) {
                echo $success;
            }
            ?>
        </div>
    </body>
</html>
