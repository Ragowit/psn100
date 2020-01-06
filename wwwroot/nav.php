<?php
$url = $_SERVER["REQUEST_URI"];
if (substr($url, 0, strlen("/leaderboard")) === "/leaderboard" || substr($url, 0, strlen("/player")) === "/player") {
    $leaderboardActiveLi = " active";
    $leaderboardActiveSpan = " <span class=\"sr-only\">(current)</span>";
} elseif (substr($url, 0, strlen("/game")) === "/game") {
    $gameActiveLi = " active";
    $gameActiveSpan = " <span class=\"sr-only\">(current)</span>";
} elseif (substr($url, 0, strlen("/trophy")) === "/trophy") {
    $trophyActiveLi = " active";
    $trophyActiveSpan = " <span class=\"sr-only\">(current)</span>";
} elseif (substr($url, 0, strlen("/avatar")) === "/avatar") {
    $avatarActiveLi = " active";
    $avatarActiveSpan = " <span class=\"sr-only\">(current)</span>";
} elseif (substr($url, 0, strlen("/about")) === "/about") {
    $aboutActiveLi = " active";
    $aboutActiveSpan = " <span class=\"sr-only\">(current)</span>";
} else {
    $homeActiveLi = " active";
    $homeActiveSpan = " <span class=\"sr-only\">(current)</span>";
}
?>
        <nav class="navbar navbar-expand-md navbar-dark fixed-top bg-dark">
            <a class="navbar-brand" href="/">PSN100</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbars" aria-controls="navbars" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbars">
                <ul class="navbar-nav mr-auto">
                    <li class="nav-item<?= $homeActiveLi; ?>">
                        <a class="nav-link" href="/">Home<?= $homeActiveSpan; ?></a>
                    </li>
                    <li class="nav-item<?= $leaderboardActiveLi; ?>">
                        <a class="nav-link" href="/leaderboard/main">Leaderboards<?= $leaderboardActiveSpan; ?></a>
                    </li>
                    <li class="nav-item<?= $gameActiveLi; ?>">
                        <a class="nav-link" href="/game">Games<?= $gameActiveSpan; ?></a>
                    </li>
                    <li class="nav-item<?= $trophyActiveLi; ?>">
                        <a class="nav-link" href="/trophy">Trophies<?= $trophyActiveSpan; ?></a>
                    </li>
                    <li class="nav-item<?= $avatarActiveLi; ?>">
                        <a class="nav-link" href="/avatar">Avatars<?= $avatarActiveSpan; ?></a>
                    </li>
                    <li class="nav-item<?= $aboutActiveLi; ?>">
                        <a class="nav-link" href="/about">About<?= $aboutActiveSpan; ?></a>
                    </li>
                </ul>
                <form action="/game" class="form-inline my-2 my-lg-0">
                    <input class="form-control mr-sm-2" name="search" type="text" placeholder="Search game..." aria-label="Search">
                    <button class="btn btn-outline-success my-2 my-sm-0" type="submit">Search</button>
                </form>
            </div>
        </nav>
