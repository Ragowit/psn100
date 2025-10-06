<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyStatusService.php';

class TrophyStatusPageResult
{
    private string $trophyInput;

    private string $statusInput;

    private ?string $message;

    public function __construct(string $trophyInput, string $statusInput, ?string $message)
    {
        $this->trophyInput = $trophyInput;
        $this->statusInput = $statusInput;
        $this->message = $message;
    }

    public function getTrophyInput(): string
    {
        return $this->trophyInput;
    }

    public function getStatusInput(): string
    {
        return $this->statusInput;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function hasMessage(): bool
    {
        return $this->message !== null;
    }
}

class TrophyStatusPage
{
    private TrophyStatusService $trophyStatusService;

    public function __construct(TrophyStatusService $trophyStatusService)
    {
        $this->trophyStatusService = $trophyStatusService;
    }

    /**
     * @param array<string, mixed> $postData
     * @param array<string, mixed> $queryData
     */
    public function handleRequest(string $requestMethod, array $postData, array $queryData): TrophyStatusPageResult
    {
        $trophyInput = '';
        $statusInput = '1';
        $message = null;

        $normalizedMethod = strtoupper($requestMethod);
        $hasTrophyPost = array_key_exists('trophy', $postData);
        $hasGamePost = array_key_exists('game', $postData);

        if ($normalizedMethod === 'POST' && ($hasTrophyPost || $hasGamePost)) {
            $status = isset($postData['status']) ? (int) $postData['status'] : 1;
            $statusInput = (string) $status;

            try {
                if (!empty($postData['game'])) {
                    $gameValue = (string) $postData['game'];

                    if (!ctype_digit($gameValue)) {
                        throw new \InvalidArgumentException('Game ID must be numeric.');
                    }

                    $gameId = (int) $gameValue;
                    $trophyIds = $this->trophyStatusService->getTrophyIdsForGame($gameId);
                    $trophyInput = implode(',', array_map('strval', $trophyIds));
                } else {
                    $trophyInput = (string) ($postData['trophy'] ?? '');
                    $trophyIds = $this->trophyStatusService->parseTrophyIds($trophyInput);
                }

                $result = $this->trophyStatusService->updateTrophies($trophyIds, $status);
                $message = $result->toHtml();
            } catch (\Throwable $exception) {
                $message = '<p>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
        } else {
            if (array_key_exists('trophy', $queryData)) {
                $trophyInput = (string) $queryData['trophy'];
            }

            if (array_key_exists('status', $queryData)) {
                $statusInput = (string) $queryData['status'];
            }
        }

        return new TrophyStatusPageResult($trophyInput, $statusInput, $message);
    }
}
