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
                OR (te.np_communication_id = 'NPWR08208_00' AND te.group_id = 'default' AND te.order_id = 9)
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
                OR (te.np_communication_id = 'NPWR08030_00' AND te.group_id = 'default' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR04361_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR04361_00' AND te.group_id = 'default' AND te.order_id = 39)
                OR (te.np_communication_id = 'NPWR04361_00' AND te.group_id = 'default' AND te.order_id = 40)
                OR (te.np_communication_id = 'NPWR03434_00' AND te.group_id = 'default' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR14011_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR14011_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR14011_00' AND te.group_id = 'default' AND te.order_id = 23)
                OR (te.np_communication_id = 'NPWR14011_00' AND te.group_id = 'default' AND te.order_id = 24)
                OR (te.np_communication_id = 'NPWR14011_00' AND te.group_id = 'default' AND te.order_id = 25)
                OR (te.np_communication_id = 'NPWR10143_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR10143_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR10143_00' AND te.group_id = 'default' AND te.order_id = 13)
                OR (te.np_communication_id = 'NPWR12133_00' AND te.group_id = 'default' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR12133_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR12133_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR09621_00' AND te.group_id = 'default' AND te.order_id = 3)
                OR (te.np_communication_id = 'NPWR09621_00' AND te.group_id = 'default' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR09621_00' AND te.group_id = 'default' AND te.order_id = 14)
                OR (te.np_communication_id = 'NPWR00934_00' AND te.group_id = 'default' AND te.order_id = 33)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 15)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 16)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 17)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 18)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 19)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 20)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 21)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 23)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 24)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 25)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 26)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 27)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 28)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 29)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 31)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 32)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 33)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 34)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.group_id = 'default' AND te.order_id = 35)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.group_id = 'default' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.group_id = 'default' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.group_id = 'default' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.group_id = 'default' AND te.order_id = 54)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.group_id = 'default' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.group_id = 'default' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.group_id = 'default' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.group_id = 'default' AND te.order_id = 54)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.group_id = 'default' AND te.order_id = 15)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.group_id = 'default' AND te.order_id = 16)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.group_id = 'default' AND te.order_id = 23)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 71)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 72)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 73)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 74)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 75)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 76)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 77)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 78)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 79)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.group_id = '004' AND te.order_id = 80)
                OR (te.np_communication_id = 'NPWR17186_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR17186_00' AND te.group_id = 'default' AND te.order_id = 34)
                OR (te.np_communication_id = 'NPWR14294_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR14294_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR14294_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 0 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 1 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 2 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 3 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 4 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 5 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 6 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 7 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 8 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 9 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 10 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 11 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.group_id = 'default' AND te.order_id = 12 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR14225_00' AND te.group_id = 'default' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR14225_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR11004_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11004_00' AND te.group_id = 'default' AND te.order_id = 15)
                OR (te.np_communication_id = 'NPWR12850_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR12850_00' AND te.group_id = 'default' AND te.order_id = 20)
                OR (te.np_communication_id = 'NPWR12851_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR12851_00' AND te.group_id = 'default' AND te.order_id = 20)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.group_id = 'default' AND te.order_id = 3)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.group_id = 'default' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.group_id = 'default' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.group_id = 'default' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.group_id = 'default' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.group_id = 'default' AND te.order_id = 3)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.group_id = 'default' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.group_id = 'default' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.group_id = 'default' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.group_id = 'default' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR10743_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR11373_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR10806_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR10806_00' AND te.group_id = 'default' AND te.order_id = 23)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.group_id = 'default' AND te.order_id = 3)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.group_id = 'default' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.group_id = 'default' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.group_id = 'default' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR17151_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR17151_00' AND te.group_id = 'default' AND te.order_id = 21)
                OR (te.np_communication_id = 'NPWR17151_00' AND te.group_id = 'default' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR11010_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11010_00' AND te.group_id = 'default' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR18341_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR18341_00' AND te.group_id = 'default' AND te.order_id = 35)
                OR (te.np_communication_id = 'NPWR05357_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR05357_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR11687_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11687_00' AND te.group_id = 'default' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR16665_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR16665_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR16665_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR17127_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR17127_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR17127_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR10742_00' AND te.group_id = 'default' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR13717_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR13717_00' AND te.group_id = 'default' AND te.order_id = 2)
                OR (te.np_communication_id = 'NPWR12262_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR12262_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR12262_00' AND te.group_id = 'default' AND te.order_id = 17)
                OR (te.np_communication_id = 'NPWR12262_00' AND te.group_id = 'default' AND te.order_id = 28)
                OR (te.np_communication_id = 'NPWR12262_00' AND te.group_id = 'default' AND te.order_id = 38)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 1)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 2)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 39)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 40)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 41)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 42)
                OR (te.np_communication_id = 'NPWR13751_00' AND te.group_id = 'default' AND te.order_id = 48)
                OR (te.np_communication_id = 'NPWR10988_00' AND te.group_id = 'default' AND te.order_id = 2)
                OR (te.np_communication_id = 'NPWR12650_00' AND te.group_id = 'default' AND te.order_id = 8 AND te.earned_date >= '2019-05-01')
                OR (te.np_communication_id = 'NPWR11297_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11297_00' AND te.group_id = 'default' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR11297_00' AND te.group_id = 'default' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR11297_00' AND te.group_id = 'default' AND te.order_id = 16)
                OR (te.np_communication_id = 'NPWR14869_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR14869_00' AND te.group_id = 'default' AND te.order_id = 2)
                OR (te.np_communication_id = 'NPWR11977_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11977_00' AND te.group_id = 'default' AND te.order_id = 18)
                OR (te.np_communication_id = 'NPWR17220_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR17220_00' AND te.group_id = 'default' AND te.order_id = 41)
                OR (te.np_communication_id = 'NPWR06434_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR06434_00' AND te.group_id = 'default' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR15045_00' AND te.group_id = 'default' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR08948_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08948_00' AND te.group_id = 'default' AND te.order_id = 18)
                OR (te.np_communication_id = 'NPWR08948_00' AND te.group_id = 'default' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR08982_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08982_00' AND te.group_id = 'default' AND te.order_id = 16)
                OR (te.np_communication_id = 'NPWR08982_00' AND te.group_id = 'default' AND te.order_id = 18)
                OR (te.np_communication_id = 'NPWR08982_00' AND te.group_id = 'default' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR19583_00' AND te.group_id = 'default' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR09492_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR09492_00' AND te.group_id = 'default' AND te.order_id = 55)
                OR (te.np_communication_id = 'NPWR17124_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR17124_00' AND te.group_id = 'default' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR17124_00' AND te.group_id = 'default' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR17124_00' AND te.group_id = 'default' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 21)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 23)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 24)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 25)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 26)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 27)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 28)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 29)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 31)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 32)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.group_id = '001' AND te.order_id = 33)
                OR (te.np_communication_id = 'NPWR11013_00' AND te.group_id = 'default' AND te.order_id = 16)
                OR (te.np_communication_id = 'NPWR16138_00' AND te.group_id = 'default' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR16138_00' AND te.group_id = 'default' AND te.order_id = 17)
                OR (te.np_communication_id = 'NPWR16138_00' AND te.group_id = 'default' AND te.order_id = 25)
                ) AND p.status = 0 GROUP BY online_id) x USING (np_communication_id)
            ORDER BY player_name");
        $query->execute();
        $possibleCheaters = $query->fetchAll();

        foreach ($possibleCheaters as $possibleCheater) {
            echo "<a href=\"/game/". $possibleCheater["game_id"] ."-". slugify($possibleCheater["game_name"]) ."/". $possibleCheater["player_name"] ."\">". $possibleCheater["player_name"] ."</a><br>";
        }
        ?>
        <br>
        FUEL:<br>
        <?php
        $query = $database->prepare("SELECT online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
            FROM player p
            JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00481_00' AND group_id = 'default' AND order_id = 33) fuel_start USING (account_id)
            JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00481_00' AND group_id = 'default' AND order_id = 34) fuel_end USING (account_id)
            WHERE p.status = 0
            HAVING time_difference <= 60
            ORDER BY online_id");
        $query->execute();
        $possibleCheaters = $query->fetchAll();

        foreach ($possibleCheaters as $possibleCheater) {
            echo "<a href=\"/game/4390-fuel/". $possibleCheater["online_id"] ."?order=date\">". $possibleCheater["online_id"] ."</a><br>";
        }
        ?>
        <br>
        SOCOM: U.S. NAVY SEALS CONFRONTATION:<br>
        <?php
        $query = $database->prepare("SELECT online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
            FROM player p
            JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00302_00' AND group_id = 'default' AND order_id = 32) socom_start USING (account_id)
            JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00302_00' AND group_id = 'default' AND order_id = 35) socom_end USING (account_id)
            WHERE p.status = 0
            HAVING time_difference <= 60
            ORDER BY online_id");
        $query->execute();
        $possibleCheaters = $query->fetchAll();

        foreach ($possibleCheaters as $possibleCheater) {
            echo "<a href=\"/game/4233-socom-us-navy-seals-confrontation/". $possibleCheater["online_id"] ."?order=date\">". $possibleCheater["online_id"] ."</a><br>";
        }
        ?>
        <br>
        Lots of completions on the same date:<br>
        <?php
        $query = $database->prepare("SELECT account_id, p.online_id, DATE(ttp.last_updated_date) AS date, COUNT(*) AS count FROM trophy_title_player ttp
            JOIN player p USING (account_id)
            WHERE ttp.progress = 100 AND p.status = 0
            GROUP BY account_id, DATE(ttp.last_updated_date)
            HAVING count >= 60
            ORDER BY count DESC");
        $query->execute();
        $possibleCheaters = $query->fetchAll();

        foreach ($possibleCheaters as $possibleCheater) {
            echo $possibleCheater["count"] .", ". $possibleCheater["date"] .", <a href=\"/player/". $possibleCheater["online_id"] ."\">". $possibleCheater["online_id"] ."</a><br>";
        }
        ?>
    </body>
</html>
