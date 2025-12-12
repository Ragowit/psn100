<?php

declare(strict_types=1);

require_once __DIR__ . '/GameDetailService.php';
require_once __DIR__ . '/GameDetailPageResult.php';
require_once __DIR__ . '/../GameStatusService.php';

class GameDetailPage
{
    private const array STATUS_OPTIONS = [
        0 => 'Normal',
        1 => 'Delisted',
        2 => 'Merged',
        3 => 'Obsolete',
        4 => 'Delisted & Obsolete',
    ];

    private const array PLATFORM_OPTIONS = [
        'PS3',
        'PSVITA',
        'PS4',
        'PSVR',
        'PS5',
        'PSVR2',
        'PC',
    ];

    private GameDetailService $gameDetailService;

    private GameStatusService $gameStatusService;

    public function __construct(GameDetailService $gameDetailService, GameStatusService $gameStatusService)
    {
        $this->gameDetailService = $gameDetailService;
        $this->gameStatusService = $gameStatusService;
    }

    /**
     * @return array<int, string>
     */
    public function getStatusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    /**
     * @return list<string>
     */
    public function getPlatformOptions(): array
    {
        return self::PLATFORM_OPTIONS;
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
                $action = $this->parseAction($postData['action'] ?? null);

                if ($action === 'update-status') {
                    [$gameDetail, $success, $error] = $this->handleStatusUpdate($postData);
                } else {
                    [$gameDetail, $success, $error] = $this->handleDetailUpdate($postData);
                }
            } elseif ($method === 'GET') {
                $npCommunicationId = null;
                $gameId = $this->parseGameId($queryParameters['game'] ?? null);

                if ($gameId !== null) {
                    $gameDetail = $this->gameDetailService->getGameDetail($gameId);
                } else {
                    $npCommunicationId = $this->parseNpCommunicationId($queryParameters['np_communication_id'] ?? null);

                    if ($npCommunicationId !== null) {
                        $gameDetail = $this->gameDetailService->getGameDetailByNpCommunicationId($npCommunicationId);
                    }
                }

                if (($gameId !== null || isset($npCommunicationId)) && $gameDetail === null) {
                    $error = '<p>Unable to find the requested game.</p>';
                }
            }
        } catch (Throwable $exception) {
            $error = '<p>' . htmlentities($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return new GameDetailPageResult($gameDetail, $success, $error);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{0: ?GameDetail, 1: ?string, 2: ?string}
     */
    private function handleDetailUpdate(array $postData): array
    {
        $gameId = $this->parseGameId($postData['game'] ?? null);

        if ($gameId === null) {
            return [null, null, '<p>Invalid game ID provided.</p>'];
        }

        $status = $this->parseStatus($postData['status'] ?? null);
        if ($status === null) {
            return [
                $this->gameDetailService->getGameDetail($gameId),
                null,
                '<p>Invalid status provided.</p>',
            ];
        }

        $gameDetail = $this->gameDetailService->updateGameDetail(
            $this->createGameDetailFromPost($gameId, $postData, $status)
        );

        $successMessages = [sprintf('<p>Game ID %d is updated.</p>', $gameDetail->getId())];
        $error = null;

        if ($status !== $gameDetail->getStatus()) {
            try {
                $statusText = $this->gameStatusService->updateGameStatus($gameId, $status);
                $successMessages[] = $this->formatStatusSuccessMessage($gameId, $statusText);
            } catch (InvalidArgumentException $exception) {
                $error = '<p>' . htmlentities($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            } catch (Throwable $exception) {
                $error = '<p>Failed to update game status. Please try again.</p>';
            }
        }

        $gameDetail = $this->gameDetailService->getGameDetail($gameId) ?? $gameDetail;

        return [$gameDetail, implode('', $successMessages), $error];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{0: ?GameDetail, 1: ?string, 2: ?string}
     */
    private function handleStatusUpdate(array $postData): array
    {
        $gameId = $this->parseGameId($postData['game'] ?? null);
        if ($gameId === null) {
            return [null, null, '<p>Invalid game ID provided.</p>'];
        }

        $status = $this->parseStatus($postData['status'] ?? null);
        if ($status === null) {
            return [
                $this->gameDetailService->getGameDetail($gameId),
                null,
                '<p>Invalid status provided.</p>',
            ];
        }

        try {
            $statusText = $this->gameStatusService->updateGameStatus($gameId, $status);
        } catch (InvalidArgumentException $exception) {
            $message = htmlentities($exception->getMessage(), ENT_QUOTES, 'UTF-8');

            return [
                $this->gameDetailService->getGameDetail($gameId),
                null,
                '<p>' . $message . '</p>',
            ];
        } catch (Throwable $exception) {
            return [
                $this->gameDetailService->getGameDetail($gameId),
                null,
                '<p>Failed to update game status. Please try again.</p>',
            ];
        }

        $gameDetail = $this->gameDetailService->getGameDetail($gameId);
        if ($gameDetail === null) {
            return [
                null,
                null,
                '<p>Unable to load the requested game.</p>',
            ];
        }

        return [
            $gameDetail,
            $this->formatStatusSuccessMessage($gameId, $statusText),
            null,
        ];
    }

    private function parseNpCommunicationId(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
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

    private function parseAction(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);

        return $trimmed === '' ? '' : strtolower($trimmed);
    }

    private function parseStatus(mixed $value): ?int
    {
        if (is_int($value)) {
            return array_key_exists($value, self::STATUS_OPTIONS) ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed[0] === '-') {
            return null;
        }

        if (!ctype_digit($trimmed)) {
            return null;
        }

        $parsed = (int) $trimmed;

        return array_key_exists($parsed, self::STATUS_OPTIONS) ? $parsed : null;
    }

    /**
     * @param array<string, mixed> $postData
     */
    private function createGameDetailFromPost(int $gameId, array $postData, int $status): GameDetail
    {
        $npCommunicationId = $this->normalizeOptionalString($postData['np_communication_id'] ?? null);
        $region = $this->normalizeOptionalString($postData['region'] ?? null);
        $psnprofilesId = $this->normalizeOptionalString($postData['psnprofiles_id'] ?? null);
        $obsoleteIds = $this->normalizeObsoleteIds($postData['obsolete_ids'] ?? null);

        return new GameDetail(
            $gameId,
            $npCommunicationId,
            (string) ($postData['name'] ?? ''),
            (string) ($postData['icon_url'] ?? ''),
            $this->normalizePlatforms($postData['platform'] ?? null),
            (string) ($postData['message'] ?? ''),
            (string) ($postData['set_version'] ?? ''),
            $region,
            $psnprofilesId,
            $status,
            $obsoleteIds
        );
    }

    private function normalizePlatforms(mixed $value): string
    {
        $candidates = [];

        if (is_array($value)) {
            $candidates = $value;
        } elseif (is_string($value)) {
            $candidates = explode(',', $value);
        }

        $selected = [];
        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $platform = strtoupper(trim($candidate));
            if ($platform === '') {
                continue;
            }

            $selected[$platform] = true;
        }

        $ordered = [];
        foreach (self::PLATFORM_OPTIONS as $platform) {
            if (isset($selected[$platform])) {
                $ordered[] = $platform;
            }
        }

        return implode(',', $ordered);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeObsoleteIds(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $segments = array_filter(
            array_map(static fn(string $segment): string => trim($segment), explode(',', $value)),
            static fn(string $segment): bool => $segment !== ''
        );

        if ($segments === []) {
            return null;
        }

        $normalized = [];
        foreach ($segments as $segment) {
            if (!ctype_digit($segment)) {
                continue;
            }

            $normalized[] = (string) (int) $segment;
        }

        if ($normalized === []) {
            return null;
        }

        $normalized = array_values(array_unique($normalized));

        return implode(',', $normalized);
    }

    private function formatStatusSuccessMessage(int $gameId, string $statusText): string
    {
        $escapedStatus = htmlentities($statusText, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<p>Game %d is now set as %s. All affected players will be updated soon, and ranks updated the next whole hour.</p>',
            $gameId,
            $escapedStatus
        );
    }
}
