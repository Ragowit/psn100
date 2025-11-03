<?php

declare(strict_types=1);

require_once __DIR__ . '/../Utility.php';

final class LogEntryFormatter
{
    private PDO $database;

    private Utility $utility;

    /**
     * @var array<int, array{id: int, name: string}|null>
     */
    private array $gameCacheById = [];

    /**
     * @var array<string, array{id: int, name: string}|null>
     */
    private array $gameCacheByNpId = [];

    public function __construct(PDO $database, Utility $utility)
    {
        $this->database = $database;
        $this->utility = $utility;
    }

    public function format(string $message): string
    {
        $formatters = [
            fn (string $message) => $this->formatTrophyHistoryMessage($message),
            fn (string $message) => $this->formatSetVersionMessage($message),
            fn (string $message) => $this->formatNewTrophiesAddedMessage($message),
            fn (string $message) => $this->formatSonyIssuesMessage($message),
        ];

        foreach ($formatters as $formatter) {
            $formatted = $formatter($message);

            if ($formatted !== null) {
                return $formatted;
            }
        }

        return $this->escape($message);
    }

    private function formatTrophyHistoryMessage(string $message): ?string
    {
        if (!preg_match('/^(Recorded new trophy_title_history entry \d+ for trophy_title\\.id )(\d+)(.*)$/', $message, $matches)) {
            return null;
        }

        $prefix = $matches[1];
        $gameIdText = $matches[2];
        $suffix = $matches[3];

        $gameId = (int) $gameIdText;
        $game = $this->fetchGameById($gameId);

        if ($game === null) {
            return $this->escape($message);
        }

        $link = $this->buildGameHistoryLink($game, $gameIdText);

        return $this->escape($prefix) . $link . $this->escape($suffix);
    }

    private function formatSetVersionMessage(string $message): ?string
    {
        if (!preg_match('/^SET VERSION for (.+)\s*\.\s*([A-Z0-9_]+),\s*([^,]+),\s*(.+)$/', $message, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $npCommunicationId = $matches[2][0];
        $npPosition = $matches[2][1];
        $groupId = $matches[3][0];
        $groupPosition = $matches[3][1];

        $prefix = substr($message, 0, $npPosition);
        $between = substr($message, $npPosition + strlen($npCommunicationId), $groupPosition - ($npPosition + strlen($npCommunicationId)));
        $suffix = substr($message, $groupPosition + strlen($groupId));

        $prefix = $prefix === false ? '' : $prefix;
        $between = $between === false ? '' : $between;
        $suffix = $suffix === false ? '' : $suffix;

        $game = $this->fetchGameByNpCommunicationId($npCommunicationId);

        if ($game === null) {
            return $this->escape($message);
        }

        $npLink = $this->buildGameLink($game, $npCommunicationId);
        $groupLink = $this->buildGroupLink($game, $groupId);

        return $this->escape($prefix) . $npLink . $this->escape($between) . $groupLink . $this->escape($suffix);
    }

    private function formatNewTrophiesAddedMessage(string $message): ?string
    {
        if (!preg_match('/^New trophies added for (.+)\s*\.\s*([A-Z0-9_]+),\s*([^,]+),\s*(.+)$/', $message, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $npCommunicationId = $matches[2][0];
        $npPosition = $matches[2][1];
        $groupId = $matches[3][0];
        $groupPosition = $matches[3][1];

        $prefix = substr($message, 0, $npPosition);
        $between = substr($message, $npPosition + strlen($npCommunicationId), $groupPosition - ($npPosition + strlen($npCommunicationId)));
        $suffix = substr($message, $groupPosition + strlen($groupId));

        $prefix = $prefix === false ? '' : $prefix;
        $between = $between === false ? '' : $between;
        $suffix = $suffix === false ? '' : $suffix;

        $game = $this->fetchGameByNpCommunicationId($npCommunicationId);

        if ($game === null) {
            return $this->escape($message);
        }

        $npLink = $this->buildGameLink($game, $npCommunicationId);
        $groupLink = $this->buildGroupLink($game, $groupId);

        return $this->escape($prefix) . $npLink . $this->escape($between) . $groupLink . $this->escape($suffix);
    }

    private function formatSonyIssuesMessage(string $message): ?string
    {
        if (!preg_match('/^(Sony issues with )(.+?)(( \(\d+\)\.)$)/', $message, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $prefix = $matches[1][0];
        $onlineId = $matches[2][0];
        $suffix = $matches[3][0];

        $link = $this->buildPlayerLink($onlineId);

        return $this->escape($prefix) . $link . $this->escape($suffix);
    }

    private function buildGameHistoryLink(array $game, string $displayText): string
    {
        $slug = $this->createSlug($game['name'] ?? '');
        $url = '/game-history/' . $game['id'];

        if ($slug !== '') {
            $url .= '-' . $slug;
        }

        return $this->buildAnchor($url, $displayText);
    }

    private function buildGameLink(array $game, string $displayText): string
    {
        $slug = $this->createSlug($game['name'] ?? '');
        $url = '/game/' . $game['id'];

        if ($slug !== '') {
            $url .= '-' . $slug;
        }

        return $this->buildAnchor($url, $displayText);
    }

    private function buildGroupLink(array $game, string $groupId): string
    {
        $slug = $this->createSlug($game['name'] ?? '');
        $url = '/game/' . $game['id'];

        if ($slug !== '') {
            $url .= '-' . $slug;
        }

        $url .= '#' . rawurlencode($groupId);

        return $this->buildAnchor($url, $groupId);
    }

    private function buildPlayerLink(string $onlineId): string
    {
        $url = '/player/' . rawurlencode($onlineId);

        return $this->buildAnchor($url, $onlineId);
    }

    private function buildAnchor(string $url, string $displayText): string
    {
        return sprintf(
            '<a href="%s">%s</a>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8')
        );
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function createSlug(?string $name): string
    {
        $slug = $this->utility->slugify($name);

        return $slug;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function fetchGameById(int $gameId): ?array
    {
        if (array_key_exists($gameId, $this->gameCacheById)) {
            return $this->gameCacheById[$gameId];
        }

        try {
            $statement = $this->database->prepare('SELECT id, name FROM trophy_title WHERE id = :id');
        } catch (PDOException $exception) {
            $this->gameCacheById[$gameId] = null;

            return null;
        }

        $statement->bindValue(':id', $gameId, PDO::PARAM_INT);

        try {
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            $this->gameCacheById[$gameId] = null;

            return null;
        }

        if (!is_array($row)) {
            $this->gameCacheById[$gameId] = null;

            return null;
        }

        $game = [
            'id' => isset($row['id']) ? (int) $row['id'] : $gameId,
            'name' => isset($row['name']) ? (string) $row['name'] : '',
        ];

        $this->gameCacheById[$gameId] = $game;

        return $game;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function fetchGameByNpCommunicationId(string $npCommunicationId): ?array
    {
        if (array_key_exists($npCommunicationId, $this->gameCacheByNpId)) {
            return $this->gameCacheByNpId[$npCommunicationId];
        }

        try {
            $statement = $this->database->prepare('SELECT id, name FROM trophy_title WHERE np_communication_id = :np LIMIT 1');
        } catch (PDOException $exception) {
            $this->gameCacheByNpId[$npCommunicationId] = null;

            return null;
        }

        $statement->bindValue(':np', $npCommunicationId, PDO::PARAM_STR);

        try {
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            $this->gameCacheByNpId[$npCommunicationId] = null;

            return null;
        }

        if (!is_array($row)) {
            $this->gameCacheByNpId[$npCommunicationId] = null;

            return null;
        }

        $gameId = isset($row['id']) ? (int) $row['id'] : 0;

        if ($gameId <= 0) {
            $this->gameCacheByNpId[$npCommunicationId] = null;

            return null;
        }

        $game = [
            'id' => $gameId,
            'name' => isset($row['name']) ? (string) $row['name'] : '',
        ];

        $this->gameCacheByNpId[$npCommunicationId] = $game;
        $this->gameCacheById[$gameId] = $game;

        return $game;
    }
}
