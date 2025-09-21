<?php

require_once __DIR__ . '/RouteResult.php';

class Router
{
    private \PDO $database;

    public function __construct(\PDO $database)
    {
        $this->database = $database;
    }

    public function dispatch(string $requestUri): RouteResult
    {
        $path = parse_url($requestUri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        $path = ltrim($path, '/');

        $segments = $path === '' ? [] : explode('/', $path);

        if ($segments === [] || $segments[0] === '') {
            return RouteResult::include('home.php');
        }

        $route = array_shift($segments);

        switch ($route) {
            case 'about':
                return $this->handleSimpleRoute($segments, 'about.php', '/about/');
            case 'avatar':
                return $this->handleSimpleRoute($segments, 'avatars.php', '/avatar/');
            case 'changelog':
                return $this->handleSimpleRoute($segments, 'changelog.php', '/changelog/');
            case 'game':
                return $this->handleGame($segments);
            case 'game-leaderboard':
                return $this->handleGameLeaderboard($segments);
            case 'game-recent-players':
                return $this->handleGameRecentPlayers($segments);
            case 'leaderboard':
                return $this->handleLeaderboard($segments);
            case 'player':
                return $this->handlePlayer($segments);
            case 'trophy':
                return $this->handleTrophy($segments);
            default:
                return RouteResult::notFound();
        }
    }

    private function handleSimpleRoute(array $segments, string $file, string $redirect): RouteResult
    {
        if ($this->hasAdditionalSegments($segments)) {
            return RouteResult::redirect($redirect);
        }

        return RouteResult::include($file);
    }

    private function handleGame(array $segments): RouteResult
    {
        if ($this->isFirstSegmentMissing($segments)) {
            return RouteResult::include('games.php');
        }

        $gameSegment = array_shift($segments);
        $gameId = $this->findGameId($gameSegment);

        if ($gameId === null) {
            return RouteResult::redirect('/game/');
        }

        $player = $segments[0] ?? null;

        return RouteResult::include('game.php', [
            'gameId' => $gameId,
            'player' => $player,
        ]);
    }

    private function handleGameLeaderboard(array $segments): RouteResult
    {
        if ($this->isFirstSegmentMissing($segments)) {
            return RouteResult::redirect('/game/');
        }

        $gameSegment = array_shift($segments);
        $gameId = $this->findGameId($gameSegment);

        if ($gameId === null) {
            return RouteResult::redirect('/game/');
        }

        $player = $segments[0] ?? null;

        return RouteResult::include('game_leaderboard.php', [
            'gameId' => $gameId,
            'player' => $player,
        ]);
    }

    private function handleGameRecentPlayers(array $segments): RouteResult
    {
        if ($this->isFirstSegmentMissing($segments)) {
            return RouteResult::redirect('/game/');
        }

        $gameSegment = array_shift($segments);
        $gameId = $this->findGameId($gameSegment);

        if ($gameId === null) {
            return RouteResult::redirect('/game/');
        }

        $player = $segments[0] ?? null;

        return RouteResult::include('game_recent_players.php', [
            'gameId' => $gameId,
            'player' => $player,
        ]);
    }

    private function handleLeaderboard(array $segments): RouteResult
    {
        if ($this->isFirstSegmentMissing($segments)) {
            return RouteResult::redirect('/leaderboard/trophy');
        }

        $view = $segments !== [] ? array_shift($segments) : '';

        switch ($view) {
            case 'main':
            case 'trophy':
                return RouteResult::include('leaderboard_main.php');
            case 'rarity':
                return RouteResult::include('leaderboard_rarity.php');
            default:
                return RouteResult::redirect('/leaderboard/trophy');
        }
    }

    private function handlePlayer(array $segments): RouteResult
    {
        if ($this->isFirstSegmentMissing($segments)) {
            return RouteResult::redirect('/leaderboard/trophy');
        }

        $onlineId = array_shift($segments);
        $accountId = $this->findAccountId($onlineId);

        if ($accountId === null) {
            return RouteResult::redirect('/player/');
        }

        $player = $this->fetchPlayer($accountId);

        if (!is_array($player) || $player === []) {
            return RouteResult::redirect('/player/');
        }

        $view = $segments !== [] ? (string) array_shift($segments) : '';

        $variables = [
            'accountId' => $accountId,
            'player' => $player,
            'onlineId' => $onlineId,
        ];

        switch ($view) {
            case '':
                return RouteResult::include('player.php', $variables);
            case 'advisor':
                return RouteResult::include('player_advisor.php', $variables);
            case 'log':
                return RouteResult::include('player_log.php', $variables);
            case 'random':
                return RouteResult::include('player_random.php', $variables);
            case 'report':
                return RouteResult::include('player_report.php', $variables);
            default:
                return RouteResult::redirect('/player/' . $onlineId);
        }
    }

    private function handleTrophy(array $segments): RouteResult
    {
        if ($this->isFirstSegmentMissing($segments)) {
            return RouteResult::include('trophies.php');
        }

        $trophySegment = array_shift($segments);
        $trophyId = $this->findTrophyId($trophySegment);

        if ($trophyId === null) {
            return RouteResult::redirect('/trophy/');
        }

        $player = $segments[0] ?? null;

        return RouteResult::include('trophy.php', [
            'trophyId' => $trophyId,
            'player' => $player,
        ]);
    }

    private function hasAdditionalSegments(array $segments): bool
    {
        foreach ($segments as $segment) {
            if ($segment !== '') {
                return true;
            }
        }

        return false;
    }

    private function isFirstSegmentMissing(array $segments): bool
    {
        return !isset($segments[0]) || $segments[0] === '';
    }

    private function findGameId(?string $segment): ?int
    {
        if ($segment === null || $segment === '') {
            return null;
        }

        $parts = explode('-', $segment);
        $id = (int) $parts[0];

        if ($id <= 0) {
            return null;
        }

        $query = $this->database->prepare('SELECT id FROM trophy_title WHERE id = :id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetchColumn();

        if ($result === false) {
            return null;
        }

        return (int) $result;
    }

    private function findTrophyId(?string $segment): ?int
    {
        if ($segment === null || $segment === '') {
            return null;
        }

        $parts = explode('-', $segment);
        $id = (int) $parts[0];

        if ($id <= 0) {
            return null;
        }

        $query = $this->database->prepare('SELECT id FROM trophy WHERE id = :id');
        $query->bindValue(':id', $id, \PDO::PARAM_INT);
        $query->execute();
        $result = $query->fetchColumn();

        if ($result === false) {
            return null;
        }

        return (int) $result;
    }

    private function findAccountId(string $onlineId): ?int
    {
        $query = $this->database->prepare('SELECT account_id FROM player WHERE online_id = :online_id');
        $query->bindValue(':online_id', $onlineId, \PDO::PARAM_STR);
        $query->execute();
        $accountId = $query->fetchColumn();

        if ($accountId === false) {
            return null;
        }

        return (int) $accountId;
    }

    private function fetchPlayer(int $accountId): ?array
    {
        $query = $this->database->prepare('
            SELECT
                p.*,
                r.ranking,
                r.rarity_ranking,
                r.ranking_country,
                r.rarity_ranking_country
            FROM
                player p
            LEFT JOIN player_ranking r ON p.account_id = r.account_id
            WHERE
                p.account_id = :account_id
        ');
        $query->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        $query->execute();
        $player = $query->fetch();

        return is_array($player) ? $player : null;
    }
}
