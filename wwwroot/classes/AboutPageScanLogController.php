<?php

declare(strict_types=1);

require_once __DIR__ . '/AboutPageDataProviderInterface.php';
require_once __DIR__ . '/AboutPageScanSummary.php';
require_once __DIR__ . '/AboutPagePlayerArraySerializer.php';
require_once __DIR__ . '/JsonResponseEmitter.php';
require_once __DIR__ . '/IpRateLimitService.php';
require_once __DIR__ . '/IpRateLimitBucket.php';
require_once __DIR__ . '/IpAddressResolver.php';

final class AboutPageScanLogController
{
    private const int DEFAULT_LIMIT = 30;
    private const int MIN_LIMIT = 1;
    private const int MAX_LIMIT = 100;

    private AboutPageDataProviderInterface $aboutPageService;
    private JsonResponseEmitter $jsonResponder;
    private ?IpRateLimitService $rateLimitService;
    private int $defaultLimit;
    private int $minLimit;
    private int $maxLimit;

    public function __construct(
        AboutPageDataProviderInterface $aboutPageService,
        JsonResponseEmitter $jsonResponder,
        ?IpRateLimitService $rateLimitService = null,
        int $defaultLimit = self::DEFAULT_LIMIT,
        int $minLimit = self::MIN_LIMIT,
        int $maxLimit = self::MAX_LIMIT
    ) {
        $this->aboutPageService = $aboutPageService;
        $this->jsonResponder = $jsonResponder;
        $this->rateLimitService = $rateLimitService;
        $this->defaultLimit = $defaultLimit;
        $this->minLimit = $minLimit;
        $this->maxLimit = $maxLimit;
    }

    #[\NoDiscard]
    public static function create(
        AboutPageDataProviderInterface $aboutPageService,
        JsonResponseEmitter $jsonResponder,
        ?IpRateLimitService $rateLimitService = null,
    ): self {
        return new self($aboutPageService, $jsonResponder, $rateLimitService);
    }

    /**
     * @param array<string, mixed> $queryParameters
     * @param array<string, mixed> $serverParameters
     */
    public function handle(array $queryParameters = [], array $serverParameters = []): void
    {
        if ($this->rateLimitService !== null) {
            $ipAddress = IpAddressResolver::resolveFromServer($serverParameters);
            if (
                !$this->rateLimitService->checkAndRecord(
                    $ipAddress,
                    IpRateLimitBucket::ScanLogPoll
                )
            ) {
                $this->jsonResponder->respond([
                    'status' => 'error',
                    'message' => 'Too many scan log requests. Please wait a moment and try again.',
                ], 429);

                return;
            }
        }

        $limit = $this->resolveLimit($queryParameters['limit'] ?? null);

        try {
            $scanSummary = $this->aboutPageService->getScanSummary();
            $scanLogPlayers = $this->aboutPageService->getScanLogPlayers($limit);

            $this->jsonResponder->respond([
                'status' => 'ok',
                'summary' => [
                    'scannedPlayers' => $scanSummary->getScannedPlayers(),
                    'newPlayers' => $scanSummary->getNewPlayers(),
                ],
                'players' => AboutPagePlayerArraySerializer::serializeCollection($scanLogPlayers),
            ]);
        } catch (\Throwable) {
            $this->jsonResponder->respond([
                'status' => 'error',
                'message' => 'Unable to load scan log data at this time.',
            ], 500);
        }
    }

    private function resolveLimit(mixed $limit): int
    {
        if ($limit instanceof \Stringable) {
            $limit = (string) $limit;
        }

        if (!is_scalar($limit)) {
            return $this->defaultLimit;
        }

        $validatedLimit = filter_var(
            $limit,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => $this->defaultLimit,
                    'min_range' => $this->minLimit,
                    'max_range' => $this->maxLimit,
                ],
            ]
        );

        if ($validatedLimit === false) {
            return $this->defaultLimit;
        }

        return (int) $validatedLimit;
    }
}
