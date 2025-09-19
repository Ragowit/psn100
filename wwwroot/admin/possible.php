<?php
require_once("../init.php");
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
    <head>
        <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <title>Admin ~ Possible Cheaters</title>
    </head>
    <body>
        <div class="p-4">
            <a href="/admin/">Back</a><br><br>
            <?php
            $sql = "SELECT x.account_id, x.online_id AS player_name, id AS game_id, name AS game_name FROM trophy_title
            JOIN (
                SELECT p.account_id, p.online_id, MIN(tt.np_communication_id) AS np_communication_id FROM trophy_earned te
                JOIN player p USING (account_id)
                JOIN trophy_title tt USING (np_communication_id) WHERE (";
            // Luftrausers
            $sql .= " (te.np_communication_id = 'NPWR05066_00' AND te.order_id = 2)
                OR (te.np_communication_id = 'NPWR05066_00' AND te.order_id = 9)";
            // Burn Zombie Burn!
            $sql .= " OR (te.np_communication_id = 'NPWR00382_00' AND te.order_id = 19)
                OR (te.np_communication_id = 'NPWR00382_00' AND te.order_id = 20)
                OR (te.np_communication_id = 'NPWR00382_00' AND te.order_id = 21)
                OR (te.np_communication_id = 'NPWR00382_00' AND te.order_id = 22)";
            // Dragon Fin Soup
            $sql .= " OR (te.np_communication_id = 'NPWR08208_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08208_00' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR08208_00' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR08208_00' AND te.order_id = 12)";
            // A-men 2 (PSVITA)
            $sql .= " OR (te.np_communication_id = 'NPWR03899_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR03899_00' AND te.order_id = 10)";
            // A-men 2 (PS3)
            $sql .= " OR (te.np_communication_id = 'NPWR04024_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR04024_00' AND te.order_id = 10)";
            // Hunter's Trophy
            $sql .= " OR (te.np_communication_id = 'NPWR01472_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR01472_00' AND te.order_id = 11)";
            // Hunter's Trophy 2 - Europa
            $sql .= " OR (te.np_communication_id = 'NPWR03558_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR03558_00' AND te.order_id = 30)";
            // IHF Handball Challenge 14
            $sql .= " OR (te.np_communication_id = 'NPWR05839_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR05839_00' AND te.order_id = 28)";
            // Just Dance 2016
            $sql .= " OR (te.np_communication_id = 'NPWR08881_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08881_00' AND te.order_id = 25)
                OR (te.np_communication_id = 'NPWR08881_00' AND te.order_id = 26)";
            // nail'd
            $sql .= " OR (te.np_communication_id = 'NPWR01064_00' AND te.order_id = 51)
                OR (te.np_communication_id = 'NPWR01064_00' AND te.order_id = 57)";
            // Pinballistik
            $sql .= " OR (te.np_communication_id = 'NPWR01685_00' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR01685_00' AND te.order_id = 15)";
            // Planet Minigolf
            $sql .= " OR (te.np_communication_id = 'NPWR00550_00' AND te.order_id = 4 AND te.earned_date >= '2015-01-01')";
            // RAMBO THE VIDEO GAME
            $sql .= " OR (te.np_communication_id = 'NPWR05256_00' AND te.order_id = 34)";
            // Rugby League Live 2 - World Cup Edition
            $sql .= " OR (te.np_communication_id = 'NPWR05666_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR05666_00' AND te.order_id = 24)";
            // UFC Personal Trainer
            $sql .= " OR (te.np_communication_id = 'NPWR01264_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR01264_00' AND te.order_id = 44)";
            // Yoostar 2
            $sql .= " OR (te.np_communication_id = 'NPWR01267_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR01267_00' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR01267_00' AND te.order_id = 31)
                OR (te.np_communication_id = 'NPWR01267_00' AND te.order_id = 32)";
            // EA SPORTS FIFA Football
            $sql .= " OR (te.np_communication_id = 'NPWR02942_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR02942_00' AND te.order_id = 22)";
            // GHOSTBUSTERS: The Video Game
            $sql .= " OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 41 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 42 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 43 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 44 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 45 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 46 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 47 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 48 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 49 AND te.earned_date >= '2012-11-16')
                OR (te.np_communication_id = 'NPWR00345_00' AND te.order_id = 50 AND te.earned_date >= '2012-11-16')";
            // Breach & Clear
            $sql .= " OR (te.np_communication_id = 'NPWR08030_00' AND te.order_id = 6)";
            // NINJA GAIDEN Σ2 PLUS
            $sql .= " OR (te.np_communication_id = 'NPWR04361_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR04361_00' AND te.order_id = 39)
                OR (te.np_communication_id = 'NPWR04361_00' AND te.order_id = 40)";
            // Night Trap - 25th Anniversary Edition
            $sql .= " OR (te.np_communication_id = 'NPWR14011_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR14011_00' AND te.order_id = 12)";
            // Tachyon Project
            $sql .= " OR (te.np_communication_id = 'NPWR10143_00' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR10143_00' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR10143_00' AND te.order_id = 13)";
            // Mr. Pumpkin Adventure
            $sql .= " OR (te.np_communication_id = 'NPWR12133_00' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR12133_00' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR12133_00' AND te.order_id = 12)";
            // SBKX Superbike World Championship
            $sql .= " OR (te.np_communication_id = 'NPWR00934_00' AND te.order_id = 33)";
            // Blues and Bullets
            $sql .= " OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 15)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 16)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 17)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 18)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 19)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 20)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 21)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 23)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 24)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 25)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 26)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 27)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 28)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 29)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 31)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 32)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 33)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 34)
                OR (te.np_communication_id = 'NPWR09796_00' AND te.order_id = 35)";
            // Boundless (EU)
            $sql .= " OR (te.np_communication_id = 'NPWR16180_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR16180_00' AND te.order_id = 54)";
            // Boundless (NA)
            $sql .= " OR (te.np_communication_id = 'NPWR16181_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR16181_00' AND te.order_id = 54)";
            // Conarium
            $sql .= " OR (te.np_communication_id = 'NPWR16018_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.order_id = 15)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.order_id = 16)
                OR (te.np_communication_id = 'NPWR16018_00' AND te.order_id = 23)";
            // Borderlands: Game of the Year Edition
            $sql .= " OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 71)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 72)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 73)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 74)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 75)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 76)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 77)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 78)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 79)
                OR (te.np_communication_id = 'NPWR01486_00' AND te.order_id = 80)";
            // Defiance 2050
            $sql .= " OR (te.np_communication_id = 'NPWR14294_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR14294_00' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR14294_00' AND te.order_id = 11)";
            // ドラゴンズドグマ オンライン (Dragon's Dogma Online)
            $sql .= " OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 0 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 1 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 2 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 3 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 4 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 5 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 6 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 7 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 8 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 9 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 10 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 11 AND te.earned_date >= '2019-12-06')
                OR (te.np_communication_id = 'NPWR04029_00' AND te.order_id = 12 AND te.earned_date >= '2019-12-06')";
            // Drunkn Bar Fight
            $sql .= " OR (te.np_communication_id = 'NPWR14225_00' AND te.order_id = 6 AND te.earned_date <= '2020-04-12')
                OR (te.np_communication_id = 'NPWR14225_00' AND te.order_id = 9 AND te.earned_date <= '2020-04-12')";
            // Dungeon Rushers
            $sql .= " OR (te.np_communication_id = 'NPWR12850_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR12850_00' AND te.order_id = 20)";
            // Dungeon Rushers
            $sql .= " OR (te.np_communication_id = 'NPWR12851_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR12851_00' AND te.order_id = 20)";
            // Epic World
            $sql .= " OR (te.np_communication_id = 'NPWR13748_00' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR13748_00' AND te.order_id = 9)";
            // Epic World
            $sql .= " OR (te.np_communication_id = 'NPWR13749_00' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR13749_00' AND te.order_id = 9)";
            // Forestry 2017 - The Simulation
            $sql .= " OR (te.np_communication_id = 'NPWR10743_00' AND te.order_id = 9)";
            // Forestry 2017 - The Simulation
            $sql .= " OR (te.np_communication_id = 'NPWR11373_00' AND te.order_id = 9)";
            // Kerbal Space Program
            $sql .= " OR (te.np_communication_id = 'NPWR10806_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR10806_00' AND te.order_id = 23)";
            // Lock's Quest
            $sql .= " OR (te.np_communication_id = 'NPWR12464_00' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR12464_00' AND te.order_id = 11)";
            // NBA 2K17
            $sql .= " OR (te.np_communication_id = 'NPWR11010_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11010_00' AND te.order_id = 22)";
            // NBA 2K20
            $sql .= " OR (te.np_communication_id = 'NPWR18341_00' AND te.order_id = 0 AND te.earned_date < '2020-03-01')
                OR (te.np_communication_id = 'NPWR18341_00' AND te.order_id = 35 AND te.earned_date < '2020-03-01')";
            // NBA LIVE 14
            $sql .= " OR (te.np_communication_id = 'NPWR05357_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR05357_00' AND te.order_id = 10)";
            // One Way Trip
            $sql .= " OR (te.np_communication_id = 'NPWR11687_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11687_00' AND te.order_id = 6)";
            // Panda Hero
            $sql .= " OR (te.np_communication_id = 'NPWR16665_00' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR16665_00' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR16665_00' AND te.order_id = 12)";
            // Panda Hero
            $sql .= " OR (te.np_communication_id = 'NPWR17127_00' AND te.order_id = 10)
                OR (te.np_communication_id = 'NPWR17127_00' AND te.order_id = 11)
                OR (te.np_communication_id = 'NPWR17127_00' AND te.order_id = 12)";
            // Professional Farmer 2017
            $sql .= " OR (te.np_communication_id = 'NPWR10742_00' AND te.order_id = 8)";
            // Rugby 18
            $sql .= " OR (te.np_communication_id = 'NPWR13717_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR13717_00' AND te.order_id = 2)";
            // Shiny
            $sql .= " OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 0 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 1 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 2 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 9 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 12 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 39 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 40 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 41 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 42 AND te.earned_date < '2020-09-24')
                OR (te.np_communication_id = 'NPWR13751_00' AND te.order_id = 48 AND te.earned_date < '2020-09-24')";
            // Solitaire
            $sql .= " OR (te.np_communication_id = 'NPWR10988_00' AND te.order_id = 2)";
            // Tango Fiesta
            $sql .= " OR (te.np_communication_id = 'NPWR11297_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11297_00' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR11297_00' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR11297_00' AND te.order_id = 16)";
            // Tethered
            $sql .= " OR (te.np_communication_id = 'NPWR11977_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR11977_00' AND te.order_id = 18)";
            // Tour de France 2019
            $sql .= " OR (te.np_communication_id = 'NPWR17220_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR17220_00' AND te.order_id = 41)";
            // Toy Soldiers War Chest
            $sql .= " OR (te.np_communication_id = 'NPWR06434_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR06434_00' AND te.order_id = 10)";
            // Trickster VR
            $sql .= " OR (te.np_communication_id = 'NPWR15045_00' AND te.order_id = 11)";
            // Wander (EU)
            $sql .= " OR (te.np_communication_id = 'NPWR08948_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08948_00' AND te.order_id = 18)
                OR (te.np_communication_id = 'NPWR08948_00' AND te.order_id = 30)";
            // Wander (NA)
            $sql .= " OR (te.np_communication_id = 'NPWR08982_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR08982_00' AND te.order_id = 18)
                OR (te.np_communication_id = 'NPWR08982_00' AND te.order_id = 30)";
            // Five Nights at Freddy's 2
            $sql .= " OR (te.np_communication_id = 'NPWR19583_00' AND te.order_id = 7 AND te.earned_date < '2022-04-01')";
            // Infinity Runner
            $sql .= " OR (te.np_communication_id = 'NPWR09492_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR09492_00' AND te.order_id = 55)";
            // Season Match Bundle - Part 1 and 2
            $sql .= " OR (te.np_communication_id = 'NPWR17124_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR17124_00' AND te.order_id = 7)
                OR (te.np_communication_id = 'NPWR17124_00' AND te.order_id = 8)
                OR (te.np_communication_id = 'NPWR17124_00' AND te.order_id = 9)";
            // Shaq Fu: A Legend Reborn
            $sql .= " OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 21)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 22)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 23)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 24)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 25)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 26)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 27)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 28)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 29)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 30)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 31)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 32)
                OR (te.np_communication_id = 'NPWR12063_00' AND te.order_id = 33)";
            // Super Mutant Alien Assault
            $sql .= " OR (te.np_communication_id = 'NPWR11013_00' AND te.order_id = 16)";
            // Felix The Reaper (EU)
            $sql .= " OR (te.np_communication_id = 'NPWR18992_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR18992_00' AND te.order_id = 23)";
            // Titan Quest
            $sql .= " OR (te.np_communication_id = 'NPWR13165_00' AND te.order_id = 31)
                OR (te.np_communication_id = 'NPWR13165_00' AND te.order_id = 39)";
            // Element Space
            $sql .= " OR (te.np_communication_id = 'MERGE_011562' AND te.order_id = 0)
                OR (te.np_communication_id = 'MERGE_011562' AND te.order_id = 17)
                OR (te.np_communication_id = 'MERGE_011562' AND te.order_id = 18)
                OR (te.np_communication_id = 'MERGE_011562' AND te.order_id = 25)
                OR (te.np_communication_id = 'MERGE_011562' AND te.order_id = 56)";
            // Hunting Simulator 2
            $sql .= " OR (te.np_communication_id = 'NPWR19903_00' AND te.order_id = 0 AND te.earned_date < '2020-09-01')
                OR (te.np_communication_id = 'NPWR19903_00' AND te.order_id = 27 AND te.earned_date < '2020-09-01')";
            // Marvel's Avengers
            $sql .= " OR (te.np_communication_id = 'NPWR16769_00' AND te.order_id = 0 AND te.earned_date < '2020-09-19')
                OR (te.np_communication_id = 'NPWR16769_00' AND te.order_id = 7 AND te.earned_date < '2020-09-19')";
            // Tokyo Tattoo Girls
            $sql .= " OR (te.np_communication_id = 'NPWR14063_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR14063_00' AND te.order_id = 35)";
            // Indivisible
            $sql .= " OR (te.np_communication_id = 'NPWR13128_00' AND te.order_id = 34)
                OR (te.np_communication_id = 'NPWR13128_00' AND te.order_id = 35)
                OR (te.np_communication_id = 'NPWR13128_00' AND te.order_id = 36)";
            // Indivisible [JP]
            $sql .= " OR (te.np_communication_id = 'NPWR19862_00' AND te.order_id = 35)";
            // Alien Spidy
            $sql .= " OR (te.np_communication_id = 'NPWR03634_00' AND te.order_id = 6)
                OR (te.np_communication_id = 'NPWR03634_00' AND te.order_id = 11)";
            // Bodycheck
            $sql .= " OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 4)
                OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 5)
                OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 9)
                OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 12)
                OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 15)
                OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 29)
                OR (te.np_communication_id = 'NPWR06410_00' AND te.order_id = 35)";
            // The Binding of Isaac: Rebirth (PS4/Vita - Japan)
            $sql .= " OR (te.np_communication_id = 'NPWR09566_00' AND te.order_id = 0)
                OR (te.np_communication_id = 'NPWR09566_00' AND te.order_id = 56)
                OR (te.np_communication_id = 'NPWR09566_00' AND te.order_id = 58)";
            // Need for Speed: The Run
            $sql .= " OR (te.np_communication_id = 'NPWR01835_00' AND te.order_id = 39 AND te.earned_date >= '2021-09-01')
                OR (te.np_communication_id = 'NPWR01835_00' AND te.order_id = 40 AND te.earned_date >= '2021-09-01')
                OR (te.np_communication_id = 'NPWR01835_00' AND te.order_id = 41 AND te.earned_date >= '2021-09-01')
                OR (te.np_communication_id = 'NPWR01835_00' AND te.order_id = 42 AND te.earned_date >= '2021-09-01')";
            $sql .= ") AND p.status != 1 GROUP BY online_id) x USING (np_communication_id)
                ORDER BY player_name";

            $query = $database->prepare($sql);
            $query->execute();
            $possibleCheaters = $query->fetchAll();

            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/". $possibleCheater["game_id"] ."-". $utility->slugify($possibleCheater["game_name"]) ."/". $possibleCheater["player_name"] ."\">". $possibleCheater["player_name"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            FUEL:<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00481_00' AND order_id = 33) fuel_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00481_00' AND order_id = 34) fuel_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4390-fuel/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>
            
            <br>
            SOCOM: U.S. NAVY SEALS CONFRONTATION:<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00302_00' AND order_id = 32) socom_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00302_00' AND order_id = 33) socom_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4233-socom-us-navy-seals-confrontation/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Resonance of Fate (Lap Two Complete < A New Beginning)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR01103_00' AND order_id = 38) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR01103_00' AND order_id = 48) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 0
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/2704-resonance-of-fate/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            End of Eternity (2周目クリア < 2周目突入)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00987_00' AND order_id = 38) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00987_00' AND order_id = 48) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 0
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/5703-end-of-eternity/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Catherine: Full Body<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR17582_00' AND order_id = 50) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR17582_00' AND order_id = 51) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 0
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4556-catherine-full-body/". $possibleCheater["online_id"] ."\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            凱薩琳FULL BODY<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR17415_00' AND order_id = 50) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR17415_00' AND order_id = 51) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 0
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/7556-kai-sa-linfull-body/". $possibleCheater["online_id"] ."\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            キャサリン・フルボディ<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14836_00' AND order_id = 50) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14836_00' AND order_id = 51) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 0
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/6489-kyasarinfurubodi/". $possibleCheater["online_id"] ."\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Lost Planet 2 (200-Chapter Playback <-> 300-Chapter Playback)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00928_00' AND order_id = 10) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00928_00' AND order_id = 11) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4237-lost-planet-2/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Lost Planet 2 (Snow Pirate Leader <-> Snow Pirate Commander)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00928_00' AND order_id = 19) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00928_00' AND order_id = 20) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4237-lost-planet-2/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Resident Evil: Revelations [PS4] (Bonus Legend <-> Bonus Demi-god)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR11777_00' AND order_id = 54) rer_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR11777_00' AND order_id = 55) rer_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4663-resident-evil-revelations/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Resident Evil: Revelations [PS4] (Meteoric Rise <-> Top of My Game)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR11777_00' AND order_id = 37) rer_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR11777_00' AND order_id = 38) rer_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4663-resident-evil-revelations/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Resident Evil: Revelations [PS3] (Bonus Legend <-> Bonus Demi-god)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03903_00' AND order_id = 49) rer_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03903_00' AND order_id = 50) rer_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3804-resident-evil-revelations/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Resident Evil: Revelations [PS3] (Meteoric Rise <-> Top of My Game)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03903_00' AND order_id = 36) rer_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03903_00' AND order_id = 37) rer_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3804-resident-evil-revelations/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Angry Birds Trilogy [PS3] (Block Breaker <-> Block Annihilator)<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03771_00' AND order_id = 30) abt_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03771_00' AND order_id = 31) abt_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3810-angry-birds-trilogy/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Terminator Salvation<br>
            <?php
            $query = $database->prepare("SELECT
                    account_id,
                    online_id,
                    trophy_count
                FROM
                    player p
                JOIN(
                    SELECT account_id,
                        COUNT(account_id) AS trophy_count
                    FROM
                        trophy_earned te
                    WHERE
                        np_communication_id = 'NPWR00623_00' AND order_id != 9 AND earned_date >=(
                        SELECT
                            earned_date
                        FROM
                            trophy_earned
                        WHERE
                            account_id = te.account_id AND np_communication_id = 'NPWR00623_00' AND order_id = 9
                    )
                GROUP BY
                    account_id
                ) trophy_counter USING(account_id)
                WHERE
                    p.status != 1
                HAVING
                    trophy_count >= 9
                ORDER BY
                    online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/294-terminator-salvation/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            F1 Race Stars:<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03734_00' AND order_id = 3) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03734_00' AND order_id = 4) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4866-f1-race-stars/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Mega Man: Legacy Collection:<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR09098_00' AND order_id = 6) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR09098_00' AND order_id = 7) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/179-mega-man-legacy-collection/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Batman: Arkham Asylum:<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00626_00' AND order_id = 31) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00626_00' AND order_id = 32) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/333-batman-arkham-asylum/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Batman: Arkham Asylum (JP):<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR01012_00' AND order_id = 31) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR01012_00' AND order_id = 32) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3131-batman-arkham-asylum/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Dead Space:<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00464_00' AND order_id = 19) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00464_00' AND order_id = 20) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 60
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3200-dead-space/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Street Fighter X Tekken [PSVITA] (Transcend All You Know <-> Your Legend Will Never Die):<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03139_00' AND order_id = 36) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR03139_00' AND order_id = 37) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 600
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3474-street-fighter-x-tekken/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Street Fighter X Tekken [PS3] (Transcend All You Know <-> Your Legend Will Never Die):<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR01781_00' AND order_id = 38) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR01781_00' AND order_id = 39) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 600
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/4253-street-fighter-x-tekken/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Fat Princess:<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, ABS(TIMESTAMPDIFF(SECOND, first_trophy, second_trophy)) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00737_00' AND order_id = 0) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR00737_00' AND order_id = 26) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference <= 300
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/279-fat-princess/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Code Vein (Determiner of Fate <-> Heirs):<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14318_00' AND order_id = 2) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14318_00' AND order_id = 39) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference >= 10
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3243-code-vein/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Code Vein (Determiner of Fate <-> To Eternity):<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14318_00' AND order_id = 2) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14318_00' AND order_id = 40) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference >= 10
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3243-code-vein/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>

            <br>
            Code Vein (Determiner of Fate <-> Dweller in the Dark):<br>
            <?php
            $query = $database->prepare("SELECT account_id, online_id, TIMESTAMPDIFF(SECOND, first_trophy, second_trophy) time_difference
                FROM player p
                JOIN (SELECT earned_date AS first_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14318_00' AND order_id = 2) trophy_start USING (account_id)
                JOIN (SELECT earned_date AS second_trophy, account_id FROM trophy_earned WHERE np_communication_id = 'NPWR14318_00' AND order_id = 41) trophy_end USING (account_id)
                WHERE p.status != 1
                HAVING time_difference >= 10
                ORDER BY online_id");
            $query->execute();
            $possibleCheaters = $query->fetchAll();
            foreach ($possibleCheaters as $possibleCheater) {
                echo "<a href=\"/game/3243-code-vein/". $possibleCheater["online_id"] ."?sort=date\">". $possibleCheater["online_id"] ." (". $possibleCheater["account_id"] .")</a><br>";
            }
            ?>
        </div>
    </body>
</html>
