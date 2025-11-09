<?php

declare(strict_types=1);

require_once __DIR__ . '/WorkerService.php';
require_once __DIR__ . '/PsnPlayerSearchResult.php';

use Tustin\PlayStation\Client;

final class PsnPlayerSearchService
{
    private const RESULT_LIMIT = 50;

    /**
     * @var callable(): iterable<Worker>
     */
    private $workerFetcher;

    /**
     * @var callable(): object
     */
    private $clientFactory;

    /**
     * @param callable(): iterable<Worker> $workerFetcher
     * @param callable(): object|null $clientFactory
     */
    public function __construct(callable $workerFetcher, ?callable $clientFactory = null)
    {
        $this->workerFetcher = $workerFetcher;
        $this->clientFactory = $clientFactory ?? static function (): object {
            return new Client();
        };
    }

    public static function fromDatabase(PDO $database): self
    {
        $workerService = new WorkerService($database);

        return new self(static fn (): array => $workerService->fetchWorkers());
    }

    public static function getResultLimit(): int
    {
        return self::RESULT_LIMIT;
    }

    /**
     * @return list<PsnPlayerSearchResult>
     */
    public function search(string $playerName): array
    {
        $normalizedPlayerName = trim($playerName);

        if ($normalizedPlayerName === '') {
            return [];
        }

        $authenticationErrors = [];
        $queryErrors = [];
        $encounteredWorker = false;
        $encounteredAuthenticatedWorker = false;

        foreach (($this->workerFetcher)() as $worker) {
            if (!$worker instanceof Worker) {
                $authenticationErrors[] = sprintf(
                    'Worker fetcher returned unexpected value (%s).',
                    get_debug_type($worker)
                );
                continue;
            }

            $encounteredWorker = true;

            $npsso = trim($worker->getNpsso());

            if ($npsso === '') {
                $authenticationErrors[] = sprintf(
                    'Worker #%d has no NPSSO token.',
                    $worker->getId()
                );
                continue;
            }

            try {
                $client = $this->createClient();
            } catch (Throwable $exception) {
                $authenticationErrors[] = sprintf(
                    'Worker #%d client creation failed: %s',
                    $worker->getId(),
                    $this->describeException($exception)
                );
                continue;
            }

            if (!method_exists($client, 'loginWithNpsso')) {
                $authenticationErrors[] = sprintf(
                    'Worker #%d client does not support NPSSO authentication.',
                    $worker->getId()
                );
                continue;
            }

            try {
                $client->loginWithNpsso($npsso);
            } catch (Throwable $exception) {
                $authenticationErrors[] = sprintf(
                    'Worker #%d login failed: %s',
                    $worker->getId(),
                    $this->describeException($exception)
                );
                continue;
            }

            $encounteredAuthenticatedWorker = true;

            try {
                return $this->queryPlayerSearchResults($client, $normalizedPlayerName);
            } catch (Throwable $exception) {
                $queryErrors[] = sprintf(
                    'Worker #%d: %s',
                    $worker->getId(),
                    $this->describeQueryException($exception, $client)
                );

                continue;
            }
        }

        if ($encounteredAuthenticatedWorker) {
            throw new RuntimeException(
                $this->buildQueryFailureMessage($normalizedPlayerName, $queryErrors, $authenticationErrors)
            );
        }

        throw new RuntimeException(
            sprintf(
                'Admin player search failed while creating an authenticated client: RuntimeException: %s',
                $this->buildAuthenticationFailureMessage($authenticationErrors, $encounteredWorker)
            )
        );
    }

    private function createClient(): object
    {
        $factory = $this->clientFactory;

        $client = $factory();

        if (!is_object($client)) {
            throw new RuntimeException('Invalid PlayStation client.');
        }

        return $client;
    }

    /**
     * @return list<PsnPlayerSearchResult>
     */
    private function queryPlayerSearchResults(object $client, string $playerName): array
    {
        $results = [];
        $count = 0;

        foreach ($client->users()->search($playerName) as $userSearchResult) {
            $results[] = PsnPlayerSearchResult::fromUserSearchResult($userSearchResult);
            $count++;

            if ($count >= self::RESULT_LIMIT) {
                break;
            }
        }

        return $results;
    }

    /**
     * @param list<string> $queryErrors
     * @param list<string> $authenticationErrors
     */
    private function buildQueryFailureMessage(string $playerName, array $queryErrors, array $authenticationErrors): string
    {
        $message = sprintf(
            'Admin player search failed while querying "%s": %s',
            $playerName,
            $queryErrors === [] ? 'no successful worker queries were executed.' : implode('; ', $queryErrors)
        );

        if ($authenticationErrors !== []) {
            $message .= sprintf(' (additional authentication issues: %s)', implode('; ', $authenticationErrors));
        }

        return $message;
    }

    /**
     * @param list<string> $authenticationErrors
     */
    private function buildAuthenticationFailureMessage(array $authenticationErrors, bool $encounteredWorker): string
    {
        if (!$encounteredWorker) {
            $details = $authenticationErrors === [] ? '' : ' Details: ' . implode(' ', $authenticationErrors);

            return 'Unable to login to any worker accounts: worker fetcher did not return any Worker instances.' . $details;
        }

        if ($authenticationErrors === []) {
            return 'Unable to login to any worker accounts: no workers with NPSSO tokens were available.';
        }

        return 'Unable to login to any worker accounts: ' . implode('; ', $authenticationErrors);
    }

    private function describeException(Throwable $exception): string
    {
        $message = $exception->getMessage();

        if ($message === '') {
            return sprintf('%s (no message provided)', get_class($exception));
        }

        return sprintf('%s: %s', get_class($exception), $message);
    }

    private function describeQueryException(Throwable $exception, object $client): string
    {
        $accessDeniedClass = 'Tustin\\Haste\\Exception\\AccessDeniedHttpException';

        if (is_a($exception, $accessDeniedClass)) {
            return $this->describeAccessDeniedException($exception, $client);
        }

        return $this->describeException($exception);
    }

    private function describeAccessDeniedException(Throwable $exception, object $client): string
    {
        $responseSummary = $this->summarizeClientResponse($client);

        $status = $responseSummary['status'] ?? 'HTTP 403';
        $details = $responseSummary['details'] ?? null;

        $message = sprintf(
            '%s: Access was denied by the PlayStation API (%s',
            get_class($exception),
            $status
        );

        if ($details !== null && $details !== '') {
            $message .= '; ' . $details;
        }

        $message .= '). Confirm the worker account can perform user searches and that its credentials are still valid.';

        return $message;
    }

    /**
     * @return array{status: string|null, details: string|null}|null
     */
    private function summarizeClientResponse(object $client): ?array
    {
        if (!method_exists($client, 'getLastResponse')) {
            return null;
        }

        try {
            $response = $client->getLastResponse();
        } catch (Throwable $exception) {
            return null;
        }

        if (!is_object($response)) {
            return null;
        }

        $status = null;
        $details = [];

        if (method_exists($response, 'getStatusCode')) {
            try {
                $statusCode = $response->getStatusCode();
            } catch (Throwable $exception) {
                $statusCode = null;
            }

            if (is_int($statusCode)) {
                $status = 'HTTP ' . $statusCode;

                if (method_exists($response, 'getReasonPhrase')) {
                    try {
                        $reason = trim((string) $response->getReasonPhrase());
                    } catch (Throwable $exception) {
                        $reason = '';
                    }

                    if ($reason !== '') {
                        $status .= ' ' . $reason;
                    }
                }
            }
        }

        if (method_exists($response, 'getBody')) {
            try {
                $body = (string) $response->getBody();
            } catch (Throwable $exception) {
                $body = '';
            }

            $body = trim($body);

            if ($body !== '') {
                $details[] = 'Body: ' . $this->truncate($body);
            }
        }

        if ($status === null && $details === []) {
            return null;
        }

        return [
            'status' => $status,
            'details' => $details === [] ? null : implode('; ', $details),
        ];
    }

    private function truncate(string $value, int $limit = 500): string
    {
        if ($limit < 4) {
            return substr($value, 0, $limit);
        }

        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit - 3) . '...';
    }
}
