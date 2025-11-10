<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnPlayerLookupService.php';
require_once __DIR__ . '/PsnPlayerLookupException.php';

final class PsnPlayerLookupRequestHandler
{
    /**
     * @return array{normalizedOnlineId: string, result: ?array, errorMessage: ?string}
     */
    public static function handle(PsnPlayerLookupService $lookupService, string $onlineId): array
    {
        $normalizedOnlineId = trim($onlineId);
        $result = null;
        $errorMessage = null;

        if ($normalizedOnlineId !== '') {
            try {
                $result = $lookupService->lookup($normalizedOnlineId);
            } catch (PsnPlayerLookupException $exception) {
                $errorMessage = $exception->getMessage();
            } catch (Throwable $exception) {
                $message = trim($exception->getMessage());

                if ($message === '') {
                    $message = 'An unexpected error occurred while looking up the player. Please try again later.';
                }

                $errorMessage = $message;
            }
        }

        return [
            'normalizedOnlineId' => $normalizedOnlineId,
            'result' => $result,
            'errorMessage' => $errorMessage,
        ];
    }
}
