<?php

declare(strict_types=1);

require_once __DIR__ . '/../Html.php';

require_once __DIR__ . '/GameDetailFormParser.php';
require_once __DIR__ . '/GameDetailService.php';
require_once __DIR__ . '/GameDetailPageResult.php';
require_once __DIR__ . '/../GameStatusService.php';
require_once __DIR__ . '/../GameAvailabilityStatus.php';

final readonly class GameDetailPage
{
    public function __construct(
        final private GameDetailService $gameDetailService,
        final private GameStatusService $gameStatusService,
        final private GameDetailFormParser $formParser = new GameDetailFormParser(),
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getStatusOptions(): array
    {
        $options = [];

        foreach (GameAvailabilityStatus::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public function getPlatformOptions(): array
    {
        return $this->formParser->getPlatformOptions();
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
            $method = ((string) ($serverParameters['REQUEST_METHOD'] ?? 'GET')) |> strtoupper(...);

            if ($method === 'POST') {
                $action = $this->formParser->parseAction($postData['action'] ?? null);

                if ($action === 'update-status') {
                    [$gameDetail, $success, $error] = $this->handleStatusUpdate($postData);
                } else {
                    [$gameDetail, $success, $error] = $this->handleDetailUpdate($postData);
                }
            } elseif ($method === 'GET') {
                $npCommunicationId = null;
                $gameId = $this->formParser->parseGameId($queryParameters['game'] ?? null);

                if ($gameId !== null) {
                    $gameDetail = $this->gameDetailService->getGameDetail($gameId);
                } else {
                    $npCommunicationId = $this->formParser->parseNpCommunicationId($queryParameters['np_communication_id'] ?? null);

                    if ($npCommunicationId !== null) {
                        $gameDetail = $this->gameDetailService->getGameDetailByNpCommunicationId($npCommunicationId);
                    }
                }

                if (($gameId !== null || isset($npCommunicationId)) && $gameDetail === null) {
                    $error = '<p>Unable to find the requested game.</p>';
                }
            }
        } catch (Throwable $exception) {
            $error = '<p>' . Html::escape($exception->getMessage()) . '</p>';
        }

        return new GameDetailPageResult($gameDetail, $success, $error);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{0: ?GameDetail, 1: ?string, 2: ?string}
     */
    private function handleDetailUpdate(array $postData): array
    {
        $gameId = $this->formParser->parseGameId($postData['game'] ?? null);

        if ($gameId === null) {
            return [null, null, '<p>Invalid game ID provided.</p>'];
        }

        $status = $this->formParser->parseStatus($postData['status'] ?? null);
        if ($status === null) {
            return [
                $this->gameDetailService->getGameDetail($gameId),
                null,
                '<p>Invalid status provided.</p>',
            ];
        }

        $gameDetail = $this->gameDetailService->updateGameDetail(
            $this->formParser->createGameDetailFromPost($gameId, $postData, $status)
        );

        $successMessages = [sprintf('<p>Game ID %d is updated.</p>', $gameDetail->getId())];
        $error = null;

        if ($status !== $gameDetail->getStatus()) {
            try {
                $statusText = $this->gameStatusService->updateGameStatus($gameId, $status);
                $successMessages[] = $this->formatStatusSuccessMessage($gameId, $statusText);
            } catch (InvalidArgumentException $exception) {
                $error = '<p>' . Html::escape($exception->getMessage()) . '</p>';
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
        $gameId = $this->formParser->parseGameId($postData['game'] ?? null);
        if ($gameId === null) {
            return [null, null, '<p>Invalid game ID provided.</p>'];
        }

        $status = $this->formParser->parseStatus($postData['status'] ?? null);
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
            $message = Html::escape($exception->getMessage());

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

    private function formatStatusSuccessMessage(int $gameId, string $statusText): string
    {
        $escapedStatus = Html::escape($statusText);

        return sprintf(
            '<p>Game %d is now set as %s. All affected players will be updated soon, and ranks updated the next whole hour.</p>',
            $gameId,
            $escapedStatus
        );
    }
}
