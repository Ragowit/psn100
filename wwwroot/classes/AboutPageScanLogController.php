<?php

declare(strict_types=1);

require_once __DIR__ . '/AboutPageService.php';
require_once __DIR__ . '/AboutPageScanSummary.php';
require_once __DIR__ . '/AboutPagePlayerArraySerializer.php';
require_once __DIR__ . '/JsonResponseEmitter.php';

final class AboutPageScanLogController
{
    private const DEFAULT_LIMIT = 30;
    private const MIN_LIMIT = 1;
    private const MAX_LIMIT = 100;

    private AboutPageService $aboutPageService;
    private JsonResponseEmitter $jsonResponder;
    private int $defaultLimit;
    private int $minLimit;
    private int $maxLimit;

    public function __construct(
        AboutPageService $aboutPageService,
        JsonResponseEmitter $jsonResponder,
        int $defaultLimit = self::DEFAULT_LIMIT,
        int $minLimit = self::MIN_LIMIT,
        int $maxLimit = self::MAX_LIMIT
    ) {
        $this->aboutPageService = $aboutPageService;
        $this->jsonResponder = $jsonResponder;
        $this->defaultLimit = $defaultLimit;
        $this->minLimit = $minLimit;
        $this->maxLimit = $maxLimit;
    }

    public static function create(AboutPageService $aboutPageService, JsonResponseEmitter $jsonResponder): self
    {
        return new self($aboutPageService, $jsonResponder);
    }

    /**
     * @param array<string, mixed> $queryParameters
     */
    public function handle(array $queryParameters = []): void
    {
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
