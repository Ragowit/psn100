<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnGameLookupService.php';
require_once __DIR__ . '/PsnGameLookupException.php';
require_once __DIR__ . '/PsnGameLookupRequestResult.php';

final class PsnGameLookupRequestHandler
{
    public static function handle(PsnGameLookupService $lookupService, string $gameId): PsnGameLookupRequestResult
    {
        $normalizedGameId = trim($gameId);
        $result = null;
        $errorMessage = null;

        if ($normalizedGameId !== '') {
            try {
                $result = $lookupService->lookupByGameId($normalizedGameId);
            } catch (PsnGameLookupException $exception) {
                $errorMessage = $exception->getMessage();
            } catch (Throwable $exception) {
                $message = trim($exception->getMessage());

                if ($message === '') {
                    $message = 'An unexpected error occurred while looking up the game. Please try again later.';
                }

                $errorMessage = $message;
            }
        }

        return new PsnGameLookupRequestResult($normalizedGameId, $result, $errorMessage);
    }
}
