<?php

declare(strict_types=1);

require_once __DIR__ . '/GameDetailService.php';
require_once __DIR__ . '/GameDetailPageResult.php';

class GameDetailPage
{
    private GameDetailService $gameDetailService;

    public function __construct(GameDetailService $gameDetailService)
    {
        $this->gameDetailService = $gameDetailService;
    }

    /**
     * @param array<string, mixed> $serverParameters
     * @param array<string, mixed> $queryParameters
     * @param array<string, mixed> $postData
     */
    public function handle(array $serverParameters, array $queryParameters, array $postData): GameDetailPageResult
    {
        $gameDetail = null;
        $success = null;
        $error = null;

        try {
            $method = strtoupper((string) ($serverParameters['REQUEST_METHOD'] ?? 'GET'));

            if ($method === 'POST') {
                $gameId = $this->parseGameId($postData['game'] ?? null);

                if ($gameId === null) {
                    $error = '<p>Invalid game ID provided.</p>';
                } else {
                    $gameDetail = $this->gameDetailService->updateGameDetail(
                        $this->createGameDetailFromPost($gameId, $postData)
                    );
                    $success = sprintf('<p>Game ID %d is updated.</p>', $gameDetail->getId());
                }
            } elseif ($method === 'GET') {
                $gameId = $this->parseGameId($queryParameters['game'] ?? null);

                if ($gameId !== null) {
                    $gameDetail = $this->gameDetailService->getGameDetail($gameId);

                    if ($gameDetail === null) {
                        $error = '<p>Unable to find the requested game.</p>';
                    }
                }
            }
        } catch (Throwable $exception) {
            $error = '<p>' . htmlentities($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return new GameDetailPageResult($gameDetail, $success, $error);
    }

    private function parseGameId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if ($trimmed[0] === '-') {
            return null;
        }

        if (!ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    /**
     * @param array<string, mixed> $postData
     */
    private function createGameDetailFromPost(int $gameId, array $postData): GameDetail
    {
        $npCommunicationId = $this->normalizeOptionalString($postData['np_communication_id'] ?? null);
        $region = $this->normalizeOptionalString($postData['region'] ?? null);
        $psnprofilesId = $this->normalizeOptionalString($postData['psnprofiles_id'] ?? null);

        return new GameDetail(
            $gameId,
            $npCommunicationId,
            (string) ($postData['name'] ?? ''),
            (string) ($postData['icon_url'] ?? ''),
            (string) ($postData['platform'] ?? ''),
            (string) ($postData['message'] ?? ''),
            (string) ($postData['set_version'] ?? ''),
            $region,
            $psnprofilesId
        );
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
