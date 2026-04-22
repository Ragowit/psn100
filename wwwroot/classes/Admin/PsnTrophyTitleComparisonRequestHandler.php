<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnTrophyTitleComparisonException.php';
require_once __DIR__ . '/PsnTrophyTitleComparisonRequestResult.php';
require_once __DIR__ . '/PsnTrophyTitleComparisonService.php';

final class PsnTrophyTitleComparisonRequestHandler
{
    public static function handle(PsnTrophyTitleComparisonService $service, string $accountId, string $source): PsnTrophyTitleComparisonRequestResult
    {
        $normalizedAccountId = trim($accountId);
        $normalizedSource = PsnTrophyTitleComparisonService::normalizeSource($source)
            ?? PsnTrophyTitleComparisonService::SOURCE_DIRECT;
        $result = null;
        $errorMessage = null;

        if ($normalizedAccountId !== '') {
            try {
                $result = $service->compareByAccountId($normalizedAccountId, $normalizedSource);
            } catch (PsnTrophyTitleComparisonException $exception) {
                $errorMessage = $exception->getMessage();
            } catch (Throwable $exception) {
                $message = trim($exception->getMessage());
                if ($message === '') {
                    $message = 'An unexpected error occurred while comparing trophy title lookups. Please try again later.';
                }

                $errorMessage = $message;
            }
        }

        return new PsnTrophyTitleComparisonRequestResult($normalizedAccountId, $normalizedSource, $result, $errorMessage);
    }
}
