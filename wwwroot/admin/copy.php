<?php
require_once("/home/psn100/public_html/init.php");
$message = "";

if (isset($_POST["parent"]) && ctype_digit(strval($_POST["parent"])) && isset($_POST["child"]) && ctype_digit(strval($_POST["child"]))) {
    $childId = $_POST["child"];
    $parentId = $_POST["parent"];

    // Sanity checks
    $query = $database->prepare("SELECT np_communication_id
        FROM   trophy_title
        WHERE  id = :id ");
    $query->bindParam(":id", $childId, PDO::PARAM_INT);
    $query->execute();
    $childNpCommunicationId = $query->fetchColumn();
    if (str_starts_with($childNpCommunicationId, "MERGE")) {
        echo "Child can't be a merge title.";
        die();
    }
    $query = $database->prepare("SELECT np_communication_id
        FROM   trophy_title
        WHERE  id = :id ");
    $query->bindParam(":id", $parentId, PDO::PARAM_INT);
    $query->execute();
    $parentNpCommunicationId = $query->fetchColumn();
    if (!str_starts_with($parentNpCommunicationId, "MERGE")) {
        echo "Parent must be a merge title.";
        die();
    }

    // Trophy Group
    $query = $database->prepare("WITH
            tg_org AS(
            SELECT
                group_id,
                NAME,
                detail,
                icon_url
            FROM
                trophy_group
            WHERE
                np_communication_id = :child_np_communication_id
        )
        UPDATE
            trophy_group tg,
            tg_org
        SET
            tg.name = tg_org.name,
            tg.detail = tg_org.detail,
            tg.icon_url = tg_org.icon_url
        WHERE
            tg.np_communication_id = :parent_np_communication_id AND tg.group_id = tg_org.group_id");
    $query->bindParam(":child_np_communication_id", $childNpCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":parent_np_communication_id", $parentNpCommunicationId, PDO::PARAM_STR);
    $query->execute();

    // Trophy
    $query = $database->prepare("WITH
            tg_org AS(
            SELECT
                group_id,
                order_id,
                hidden,
                NAME,
                detail,
                icon_url,
                progress_target_value,
                reward_name,
                reward_image_url
            FROM
                trophy
            WHERE
                np_communication_id = :child_np_communication_id
        )
        UPDATE
            trophy tg,
            tg_org
        SET
            tg.hidden = tg_org.hidden,
            tg.name = tg_org.name,
            tg.detail = tg_org.detail,
            tg.icon_url = tg_org.icon_url,
            tg.progress_target_value = tg_org.progress_target_value,
            tg.reward_name = tg_org.reward_name,
            tg.reward_image_url = tg_org.reward_image_url
        WHERE
            tg.np_communication_id = :parent_np_communication_id AND tg.group_id = tg_org.group_id AND tg.order_id = tg_org.order_id");
    $query->bindParam(":child_np_communication_id", $childNpCommunicationId, PDO::PARAM_STR);
    $query->bindParam(":parent_np_communication_id", $parentNpCommunicationId, PDO::PARAM_STR);
    $query->execute();

    $message .= "The group and trophy data have been copied.";
}
?>
<!doctype html>
<html lang="en">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>Admin ~ Copy</title>
    </head>
    <body>
        <a href="/admin/">Back</a><br><br>
        <form method="post" autocomplete="off">
            Game Child ID:<br>
            <input type="number" name="child"><br>
            Game Parent ID:<br>
            <input type="number" name="parent"><br>
            <br>
            <input type="submit" value="Submit">
        </form>

        <?= $message; ?>
    </body>
</html>
