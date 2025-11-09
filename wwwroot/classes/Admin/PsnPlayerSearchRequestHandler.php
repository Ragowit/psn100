<?php

declare(strict_types=1);

require_once __DIR__ . '/PsnPlayerSearchRateLimitException.php';
require_once __DIR__ . '/PsnPlayerSearchService.php';

final class PsnPlayerSearchRequestHandler
{
    /**
     * @return array{normalizedSearchTerm: string, results: list<PsnPlayerSearchResult>, errorMessage: ?string}
     */
    public static function handle(PsnPlayerSearchService $searchService, string $searchTerm): array
    {
        $normalizedSearchTerm = trim($searchTerm);
        $results = [];
        $errorMessage = null;

        if ($normalizedSearchTerm !== '') {
            try {
                $results = $searchService->search($normalizedSearchTerm);
            } catch (PsnPlayerSearchRateLimitException $rateLimitException) {
                $retryAt = $rateLimitException->getRetryAt();

                $errorMessage = $retryAt === null
                    ? 'The PlayStation Network rate limited player search. Please try again later.'
                    : sprintf(
                        'The PlayStation Network rate limited player search until %s.',
                        $retryAt->format(DateTimeInterface::RFC3339)
                    );
            } catch (Throwable $exception) {
                $message = trim($exception->getMessage());

                if ($message === '') {
                    $message = 'An unexpected error occurred while searching for players. Please try again later.';
                }

                $errorMessage = $message;
            }
        }

        return [
            'normalizedSearchTerm' => $normalizedSearchTerm,
            'results' => $results,
            'errorMessage' => $errorMessage,
        ];
    }
}
