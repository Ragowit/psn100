<?php
$url = $_SERVER["REQUEST_URI"];
$leaderboardActive = "";
$gameActive = "";
$trophyActive = "";
$avatarActive = "";
$aboutActive = "";
$homeActive = "";

if (str_starts_with($url, "/leaderboard") || str_starts_with($url, "/player")) {
    $leaderboardActive = " active";
} elseif (str_starts_with($url, "/game")) {
    $gameActive = " active";
} elseif (str_starts_with($url, "/trophy")) {
    $trophyActive = " active";
} elseif (str_starts_with($url, "/avatar")) {
    $avatarActive = " active";
} elseif (str_starts_with($url, "/about")) {
    $aboutActive = " active";
} else {
    $homeActive = " active";
}
?>

<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-2">
    <div class="container">
        <a class="navbar-brand" href="/">
            <img src="/img/logo-via-logohub.png" alt="PSN 100%" height="24">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
            <form action="/game" class="d-flex" role="search">
                <input type="hidden" name="sort" value="<?= $_GET["sort"]; ?>">
                <input type="hidden" name="player" value="<?= $_GET["player"]; ?>">
                <input type="hidden" name="filter" value="<?= $_GET["filter"]; ?>">
                <input class="form-control me-2" name="search" type="search" placeholder="Search game..." aria-label="Search" value="<?= htmlentities($_GET["search"]); ?>">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>

            <ul class="navbar-nav ms-auto mb-2 mb-md-0">
                <li class="nav-item">
                    <a class="nav-link<?= $homeActive; ?>" href="/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $leaderboardActive; ?>" href="/leaderboard/main">Leaderboards</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $gameActive; ?>" href="/game">Games</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $trophyActive; ?>" href="/trophy">Trophies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $avatarActive; ?>" href="/avatar">Avatars</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $aboutActive; ?>" href="/about">About</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
