<?php
require_once __DIR__ . '/classes/NavigationState.php';

$navigationState = NavigationState::fromGlobals($_SERVER, $_GET);
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
                <input type="hidden" name="sort" value="<?= $navigationState->getSort(); ?>">
                <input type="hidden" name="player" value="<?= $navigationState->getPlayer(); ?>">
                <input type="hidden" name="filter" value="<?= $navigationState->getFilter(); ?>">
                <input class="form-control me-2" name="search" type="search" placeholder="Search game..." aria-label="Search" value="<?= $navigationState->getSearch(); ?>">
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>

            <ul class="navbar-nav ms-auto mb-2 mb-md-0">
                <li class="nav-item">
                    <a class="nav-link<?= $navigationState->getHomeClass(); ?>" href="/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $navigationState->getLeaderboardClass(); ?>" href="/leaderboard/trophy">Leaderboards</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $navigationState->getGameClass(); ?>" href="/game">Games</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $navigationState->getTrophyClass(); ?>" href="/trophy">Trophies</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $navigationState->getAvatarClass(); ?>" href="/avatar">Avatars</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link<?= $navigationState->getAboutClass(); ?>" href="/about">About</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
