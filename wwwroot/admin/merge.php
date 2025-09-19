<?php
ini_set("max_execution_time", "0");
ini_set("max_input_time", "0");
ini_set("mysql.connect_timeout", "0");
set_time_limit(0);
require_once("../init.php");
require_once("../classes/TrophyMergeService.php");

$message = "";
$mergeService = new TrophyMergeService($database);

try {
    if (isset($_POST["trophyparent"]) && ctype_digit(strval($_POST["trophyparent"])) && isset($_POST["trophychild"])) {
        $childTrophiesRaw = array_map('trim', explode(',', (string) $_POST["trophychild"]));
        $childTrophyIds = [];

        foreach ($childTrophiesRaw as $childId) {
            if ($childId === '') {
                continue;
            }

            if (!ctype_digit($childId)) {
                throw new InvalidArgumentException('Child trophy ids must be numeric.');
            }

            $childTrophyIds[] = (int) $childId;
        }

        $message = $mergeService->mergeSpecificTrophies((int) $_POST["trophyparent"], $childTrophyIds);
    } elseif (isset($_POST["parent"]) && ctype_digit(strval($_POST["parent"])) && isset($_POST["child"]) && ctype_digit(strval($_POST["child"]))) {
        $childId = (int) $_POST["child"];
        $parentId = (int) $_POST["parent"];
        $method = strtolower((string) ($_POST["method"] ?? 'order'));

        $message = $mergeService->mergeGames($childId, $parentId, $method);
    } elseif (isset($_POST["child"]) && ctype_digit(strval($_POST["child"]))) {
        $message = $mergeService->cloneGame((int) $_POST["child"]);
    }
} catch (InvalidArgumentException | RuntimeException $exception) {
    $message = $exception->getMessage();
} catch (Throwable $exception) {
    $message = 'An unexpected error occurred: ' . $exception->getMessage();
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Merge Games</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <form method="post" autocomplete="off">
                Game Child ID:<br>
                <input type="number" name="child"><br>
                Game Parent ID:<br>
                <input type="number" name="parent"><br>
                Method:<br>
                <select name="method">
                    <option value="order">Order</option>
                    <option value="name">Name</option>
                    <option value="icon">Icon</option>
                </select><br><br>
                Trophy Child ID:<br>
                <input type="text" name="trophychild"><br>
                Trophy Parent ID:<br>
                <input type="number" name="trophyparent"><br><br>
                <input type="submit" value="Submit">
            </form>

            <?= $message; ?>
        </div>
    </body>
</html>
